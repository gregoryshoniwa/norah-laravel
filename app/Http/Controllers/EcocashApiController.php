<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChargeCalculator;
use App\Services\EcoCashPaymentService;
use App\Services\TransactionAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Direct (server-to-server) EcoCash API.
 *
 * Lets an authenticated MERCHANT or ADMIN account trigger an EcoCash USSD
 * prompt on a customer's phone without sending the customer through the
 * hosted checkout page. The rows written here are identical in shape to the
 * ones the checkout writes, so the payment shows up in every dashboard,
 * transaction list and payout calculation with no further changes.
 *
 * Lifecycle:
 *   POST /api/v1/ecocash/pay            -> CONFIRM row (PENDING), prompt sent
 *   GET  /api/v1/ecocash/status/{trace} -> polls EcoCash, finalizes on completion
 *   EcoCash notify / reconcile command  -> also finalize, so polling is optional
 *   Merchant webhook (web_service_url)  -> fired on COMPLETED / FAILED
 */
class EcocashApiController extends Controller
{
    public function __construct(
        protected EcoCashPaymentService $ecocashService,
        protected ChargeCalculator $chargeCalculator,
        protected TransactionAuditService $transactionAuditService,
        protected TransactionController $transactionController,
    ) {
    }

    public function pay(Request $request)
    {
        $user = JWTAuth::user();
        if (!$user || !in_array($user->role, ['MERCHANT', 'ADMIN'], true)) {
            return $this->error('Unauthorized. Only MERCHANT or ADMIN accounts can trigger EcoCash payments.', 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'phoneNumber' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s\-]{7,17}$/'],
            'customerReference' => 'sometimes|nullable|string|max:60',
        ], [
            'phoneNumber.regex' => 'phoneNumber must be a valid mobile number (digits only, e.g. 0771234567 or 263771234567).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = round((float) $request->input('amount'), 2);
        $currency = strtoupper($request->input('currency'));
        $phoneNumber = $this->normalisePhoneNumber($request->input('phoneNumber'));
        $customerReference = $this->normaliseCustomerReference($request->input('customerReference'));
        $trace = Str::uuid()->toString();

        $this->audit([
            'user_id' => $user->id,
            'trace' => $trace,
            'payment_method' => 'ECOCASH',
            'stage' => 'CONFIRMATION',
            'event' => 'ECOCASH_DIRECT_REQUEST_RECEIVED',
            'level' => 'INFO',
            'provider' => 'ECOCASH',
            'request_payload' => $request->all(),
            'meta_data' => ['channel' => 'API'],
        ]);

        // Idempotency: a merchant retrying the same customerReference while the
        // previous prompt is still pending gets the existing transaction back
        // rather than a second USSD prompt on the customer's phone.
        if ($customerReference !== null) {
            $existing = Transaction::where('user_id', $user->id)
                ->where('payment_method', 'ECOCASH')
                ->where('customer_reference', $customerReference)
                ->where('type', 'CONFIRM')
                ->where('status', 'PENDING')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                $this->audit([
                    'transaction_id' => $existing->id,
                    'user_id' => $user->id,
                    'trace' => $existing->trace,
                    'reference' => $existing->reference,
                    'payment_method' => 'ECOCASH',
                    'stage' => 'CONFIRMATION',
                    'event' => 'ECOCASH_DIRECT_DUPLICATE_RETURNED',
                    'level' => 'INFO',
                    'provider' => 'ECOCASH',
                    'meta_data' => ['duplicate_of_trace' => $trace],
                ]);

                return $this->pendingResponse($existing, true);
            }
        }

        // Charges are computed server-side from the account's configured
        // charge tables. Nothing about the amount breakdown is trusted from
        // the caller.
        try {
            $quote = $this->chargeCalculator->quote($user, $amount, $currency);
        } catch (\RuntimeException $e) {
            $this->audit([
                'user_id' => $user->id,
                'trace' => $trace,
                'payment_method' => 'ECOCASH',
                'stage' => 'VALIDATION',
                'event' => 'ECOCASH_DIRECT_CHARGE_LOOKUP_FAILED',
                'level' => 'ERROR',
                'provider' => 'ECOCASH',
                'response_payload' => ['message' => $e->getMessage()],
            ]);
            return $this->error($e->getMessage(), 422);
        }

        $charge = number_format($quote['total_charge'], 2, '.', '');
        $total = number_format($quote['total_amount'], 2, '.', '');
        $merchantUid = $this->merchantUidFor($user);

        // This is the same shape the checkout page posts to /transactions/confirmation,
        // so the CONFIRM row is indistinguishable from a web checkout except for
        // additional_data.channel.
        $confirmPayload = [
            'paymentMethod' => 'ECOCASH',
            'amount' => number_format($amount, 2, '.', ''),
            'charge' => $charge,
            'total' => $total,
            'currency' => $currency,
            'user' => $user->email,
            'phoneNumber' => $phoneNumber,
            'narration' => 'ECOCASH Payment',
            'type' => 'PAYMENT',
            'customerReference' => $customerReference,
            'merchantUid' => $merchantUid,
            'channel' => 'API',
        ];

        try {
            $providerResponse = $this->ecocashService->createPaymentRequest(
                $confirmPayload + ['_auditTrace' => $trace]
            );
        } catch (\Throwable $e) {
            $failed = $this->saveFailedRow($user, $trace, $confirmPayload, $e->getMessage(), $merchantUid);

            $this->audit([
                'transaction_id' => $failed?->id,
                'user_id' => $user->id,
                'trace' => $trace,
                'payment_method' => 'ECOCASH',
                'stage' => 'CONFIRMATION',
                'event' => 'ECOCASH_DIRECT_PROVIDER_FAILED',
                'level' => 'ERROR',
                'provider' => 'ECOCASH',
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            return response()->json([
                'success' => false,
                'status' => 'FAILED',
                'trace' => $trace,
                'message' => $e->getMessage(),
            ], 502);
        }

        $reference = $providerResponse['referenceCode'] ?? $providerResponse['clientCorrelator'] ?? null;

        $transaction = new Transaction();
        $transaction->type = 'CONFIRM';
        $transaction->pan = $phoneNumber;
        $transaction->trace = $trace;
        $transaction->reference = $reference;
        $transaction->customer_reference = $customerReference;
        $transaction->currency = $currency;
        $transaction->amount = number_format($amount, 2, '.', '');
        $transaction->charge = $charge;
        $transaction->status = 'PENDING';
        $transaction->payment_method = 'ECOCASH';
        $transaction->numeric_amount = number_format($amount, 2, '.', '');
        $transaction->response_code = '00';
        $transaction->request = json_encode($confirmPayload);
        $transaction->response = json_encode($providerResponse);
        $transaction->merchant_uid = $merchantUid ?? '';
        $transaction->user_name = $user->email;
        $transaction->user_id = $user->id;
        $transaction->user_type = $quote['user_type'];
        $transaction->additional_data = json_encode(['channel' => 'API']);
        $transaction->save();

        $this->transactionAuditService->linkToTransaction($trace, $transaction->id);
        $this->audit([
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'trace' => $trace,
            'reference' => $reference,
            'payment_method' => 'ECOCASH',
            'stage' => 'CONFIRMATION',
            'event' => 'TRANSACTION_CONFIRMATION_SAVED',
            'level' => 'INFO',
            'provider' => 'ECOCASH',
            'request_payload' => $confirmPayload,
            'response_payload' => $providerResponse,
            'meta_data' => ['status' => 'PENDING', 'transaction_type' => 'CONFIRM', 'channel' => 'API'],
        ]);

        return $this->pendingResponse($transaction, false);
    }

    /**
     * Poll the status of a direct EcoCash payment. Delegates to the same
     * status/finalization routine the checkout page uses so a COMPLETED
     * inquiry writes the PAYMENT row, the charge rows and fires the webhook.
     */
    public function status(Request $request, string $trace)
    {
        $user = JWTAuth::user();
        if (!$user) {
            return $this->error('Unauthorized.', 401);
        }

        $transaction = Transaction::where('trace', $trace)
            ->whereIn('type', ['CONFIRM', 'PAYMENT'])
            ->orderBy('id')
            ->first();

        if (!$transaction) {
            return $this->error('Transaction not found.', 404);
        }

        if (!$this->canView($user, $transaction)) {
            return $this->error('Unauthorized access. You can only view your own transactions.', 403);
        }

        $upstream = $this->transactionController->checkTransactionStatus(new Request(['trace' => $trace]));
        $data = $upstream->getData(true);

        // Re-shape into the API contract; the checkout response carries
        // browser-only fields like returnUrl and pollInterval.
        $txn = $data['transaction'] ?? null;
        return response()->json([
            'success' => (bool) ($data['success'] ?? false),
            'status' => $data['status'] ?? 'PENDING',
            'trace' => $trace,
            'responseCode' => $data['responseCode'] ?? null,
            'message' => $data['responseMessage'] ?? $data['message'] ?? null,
            'shouldPoll' => ($data['status'] ?? 'PENDING') === 'PENDING',
            'transaction' => $txn ? $this->publicTransaction($txn) : null,
        ], $upstream->getStatusCode());
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function pendingResponse(Transaction $transaction, bool $duplicate)
    {
        return response()->json([
            'success' => true,
            'status' => 'PENDING',
            'duplicate' => $duplicate,
            'message' => $duplicate
                ? 'A pending EcoCash request already exists for this customerReference.'
                : 'EcoCash payment prompt sent. Ask the customer to approve it on their phone.',
            'trace' => $transaction->trace,
            'reference' => $transaction->reference,
            'amount' => $transaction->amount,
            'charge' => $transaction->charge,
            'totalAmount' => number_format((float) $transaction->amount + (float) $transaction->charge, 2, '.', ''),
            'currency' => $transaction->currency,
            'phoneNumber' => $transaction->pan,
            'customerReference' => $transaction->customer_reference,
            'statusUrl' => url("/api/v1/ecocash/status/{$transaction->trace}"),
            'shouldPoll' => true,
            'pollIntervalMs' => 5000,
        ], $duplicate ? 200 : 202);
    }

    protected function publicTransaction(array $txn): array
    {
        return [
            'id' => $txn['id'] ?? null,
            'type' => $txn['type'] ?? null,
            'status' => $txn['status'] ?? null,
            'trace' => $txn['trace'] ?? null,
            'reference' => $txn['reference'] ?? null,
            'credit_reference' => $txn['credit_reference'] ?? null,
            'customer_reference' => $txn['customer_reference'] ?? null,
            'amount' => $txn['amount'] ?? null,
            'charge' => $txn['charge'] ?? null,
            'currency' => $txn['currency'] ?? null,
            'payment_method' => $txn['payment_method'] ?? null,
            'response_code' => $txn['response_code'] ?? null,
            'error_message' => $txn['error_message'] ?? null,
            'created_at' => $txn['created_at'] ?? null,
            'updated_at' => $txn['updated_at'] ?? null,
        ];
    }

    protected function canView(User $user, Transaction $transaction): bool
    {
        if ($user->role === 'SUPER') {
            return true;
        }
        if ((int) $transaction->user_id === (int) $user->id) {
            return true;
        }
        if ($user->role === 'ADMIN') {
            $owner = User::find($transaction->user_id);
            return $owner && (int) $owner->primary_user === (int) $user->id;
        }
        return false;
    }

    protected function merchantUidFor(User $user): ?string
    {
        if ($user->role !== 'MERCHANT') {
            return null;
        }
        return Merchant::where('user_id', $user->id)->value('merchant_uid');
    }

    protected function saveFailedRow(User $user, string $trace, array $payload, string $message, ?string $merchantUid): ?Transaction
    {
        try {
            $failed = new Transaction();
            $failed->type = 'PAYMENT';
            $failed->pan = $payload['phoneNumber'];
            $failed->trace = $trace;
            $failed->customer_reference = $payload['customerReference'];
            $failed->currency = $payload['currency'];
            $failed->amount = $payload['amount'];
            $failed->charge = $payload['charge'];
            $failed->status = 'FAILED';
            $failed->payment_method = 'ECOCASH';
            $failed->numeric_amount = $payload['amount'];
            $failed->response_code = '01';
            $failed->error_message = $message;
            $failed->request = json_encode($payload);
            $failed->merchant_uid = $merchantUid ?? '';
            $failed->user_name = $user->email;
            $failed->user_id = $user->id;
            $failed->user_type = $user->role === 'ADMIN' ? 'U' : 'M';
            $failed->additional_data = json_encode(['channel' => 'API']);
            $failed->save();

            $this->transactionAuditService->linkToTransaction($trace, $failed->id);
            return $failed;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    protected function normalisePhoneNumber(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }

    protected function normaliseCustomerReference($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, 60);
    }

    protected function error(string $message, int $status)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    protected function audit(array $data): void
    {
        $this->transactionAuditService->record($data);
    }
}
