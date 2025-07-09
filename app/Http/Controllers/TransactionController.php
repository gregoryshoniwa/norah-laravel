<?php

namespace App\Http\Controllers;

use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use App\Models\Merchant;
use App\Models\SystemCharge;
use App\Models\Charge;
use App\Services\EcoCashPaymentService;
use App\Services\InnBucksPaymentService;
use App\Services\OmariPaymentService;
use App\Services\ZimswitchPaymentService;
use App\Services\IVeriPaymentService;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $innbucksService;
    protected $ecocashService;
    protected $omariService;
    protected $zimswitchService;
    protected $iveriService;


    public function __construct(
        InnBucksPaymentService $innbucksService,
        EcoCashPaymentService $ecocashService,
        OmariPaymentService $omariService,
        ZimswitchPaymentService $zimswitchService,
        IVeriPaymentService $iveriService
    )
    {
        $this->innbucksService = $innbucksService;
        $this->ecocashService = $ecocashService;
        $this->omariService = $omariService;
        $this->zimswitchService = $zimswitchService;
        $this->iveriService = $iveriService;
    }

    public function confirmTransaction(Request $request)
    {
        $paymentMethod = $request->input('paymentMethod');
        $response = null;

        try {
            if ($paymentMethod === 'INNBUCKS') {
                $response = $this->innbucksService->createPaymentRequest($request->all());
                $reference = $response['code'] ?? null;
                $requiresOtp = false;
            } elseif ($paymentMethod === 'ECOCASH') {
                $response = $this->ecocashService->createPaymentRequest($request->all());
                $reference = $response['referenceCode'] ?? null;
                $requiresOtp = false;
            } elseif ($paymentMethod === 'OMARI') {
                $response = $this->omariService->createPaymentRequest($request->all());
                $reference = $response['reference'] ?? null;

                // For Omari, check if authorization was successful, which means OTP was sent
                $requiresOtp = isset($response['responseCode']) && $response['responseCode'] === '000' && isset($response['message']) && $response['message'] === 'Auth Success';

                // Store if this is an error response
                $hasError = isset($response['error']) && $response['error'] === true;
            } elseif ($paymentMethod === 'ZIMSWITCH') {
                // Generate a trace ID before calling prepareCheckout
                $trace = Str::uuid()->toString();
                $data = $request->all();
                $data['trace'] = $trace;

                $response = $this->zimswitchService->prepareCheckout($data);
                $reference = $response['checkoutId'] ?? null;  // Use the checkout ID as reference
                $requiresOtp = false;  // No OTP required for Zimswitch
                $hasError = isset($response['error']) && $response['error'] === true;
            } elseif ($paymentMethod === 'VISA_MASTER') {
                // Generate a trace ID before processing payment
                $trace = Str::uuid()->toString();
                $data = $request->all();
                $data['trace'] = $trace;

                // Process the card payment through iVeri
                $response = $this->iveriService->processPayment($data);
                $reference = $response['reference'] ?? null;
                $requiresOtp = false;  // May change based on 3D Secure requirements
                $hasError = isset($response['error']) && $response['error'] === true;

                // Check for direct 3D Secure redirect URL
                if (isset($response['redirectUrl'])) {
                    return response()->json([
                        'success' => true,
                        'redirectUrl' => $response['redirectUrl'],
                        'trace' => $trace,
                        'reference' => $response['reference'] ?? null,
                        'returnUrl' => url('/payment/complete?status=success')
                    ]);
                }

                // Check for ACS form data that needs to be posted to 3D Secure
                if (isset($response['acsUrl']) && isset($response['acsPayload'])) {
                    return response()->json([
                        'success' => true,
                        'acsUrl' => $response['acsUrl'],
                        'acsPayload' => $response['acsPayload'],
                        'trace' => $trace,
                        'reference' => $response['reference'] ?? null,
                        'returnUrl' => url('/payment/complete?status=success')
                    ]);
                }
            }

            $user = User::where('email', $request['user'])->first();

            if($user->role === 'MERCHANT') {
                $merchant = Merchant::where('user_id', $user->id)->first();
                $returnUrl = $merchant->return_url;
            }
            if($user->role === 'ADMIN') {
                $returnUrl = $user->return_url;
            }

            // Save the confirmation to the database
            $transaction = new Transaction();
            $transaction->type = 'CONFIRM';
            $transaction->pan = $request->input('pan') ?? $request->input('phoneNumber');
            $transaction->expiry_date = $request->input('expiryDate');
            // Use the trace we generated for Zimswitch, or generate a new one for other payment methods
            $transaction->trace = $paymentMethod === 'ZIMSWITCH' ? $trace : Str::uuid()->toString();
            $transaction->reference = $reference;
            $transaction->currency = $request->input('currency');
            $transaction->amount = number_format((float) $request->input('amount'), 2, '.', '');
            $transaction->charge = number_format((float) $request->input('charge'), 2, '.', '');
            $transaction->status = 'PENDING';
            $transaction->payment_method = $request->input('paymentMethod');
            $transaction->numeric_amount = number_format((float) $request->input('amount'), 2, '.', '');
            $transaction->response_code = '00';
            $transaction->request = json_encode($request->all());
            $transaction->response = json_encode($response);
            $transaction->merchant_uid = $request->input('merchantUid') ?? '';
            $transaction->user_name = $user->email;
            $transaction->user_id = $user->id;
            $transaction->user_type = $user->role === 'ADMIN' ? 'U' : 'M';

            $transaction->save();

            // Handle payment errors for Omari or Zimswitch
            if (($paymentMethod === 'OMARI' && isset($hasError) && $hasError) ||
                ($paymentMethod === 'ZIMSWITCH' && isset($response['error']) && $response['error'] === true)) {

                // Update transaction with failed status
                $transaction->update([
                    'status' => 'FAILED',
                    'response_code' => $response['responseCode'] ?? '01',
                    'error_message' => $response['message'] ?? 'Authorization failed',
                    'type' => 'PAYMENT'
                ]);

                // Return error response
                return response()->json([
                    'success' => false,
                    'status' => 'FAILED',
                    'message' => $response['message'] ?? 'Authorization failed',
                    'responseCode' => $response['responseCode'] ?? '01',
                    'trace' => $transaction->trace,
                    'returnUrl' => $returnUrl
                ]);
            }

            // Special response for Zimswitch integrated in Vue.js
            if ($paymentMethod === 'ZIMSWITCH' && isset($response['success']) && $response['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Zimswitch checkout prepared successfully.',
                    'trace' => $transaction->trace,
                    'returnUrl' => $returnUrl,
                    'checkoutId' => $response['checkoutId'],
                    'paymentUrl' => $response['paymentUrl'],
                    'authConfig' => $response['authConfig'],
                    'integrateInVue' => true, // Flag to tell frontend to integrate in Vue.js
                    'shouldPoll' => false,
                    'amount' => $response['amount'],
                    'currency' => $response['currency'],
                    'paymode' => $response['paymode']
                ]);
            }

            // Standard response for other payment methods
            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'Transaction confirmed successfully.',
                'trace' => $transaction->trace,
                'returnUrl' => $returnUrl,
                'transaction' => $transaction,
                'shouldPoll' => true,
                'requiresOtp' => $requiresOtp ?? false, // Flag to tell frontend OTP is needed
                'otpReference' => $requiresOtp ? ($response['otpReference'] ?? null) : null, // OTP reference if available
                'pollInterval' => $paymentMethod === 'INNBUCKS' ? 30000 : ($paymentMethod === 'OMARI' ? 10000 : 5000) // 30s for INNBUCKS, 10s for OMARI, 5s for others
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkTransactionStatus(Request $request)
    {
        $trace = $request->trace;

        try {
            $transaction = Transaction::where('trace', $trace)
                            ->whereIn('type', ['CONFIRM', 'PAYMENT'])
                            ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found.',
                ], 404);
            }

            // Check if cancelled
            if ($transaction->status === 'CANCELLED') {
                return $this->handleCancelledTransaction($transaction);
            }

            // If already completed
            if ($transaction->type === 'PAYMENT' && $transaction->status === 'COMPLETED') {
                return $this->finalizeSuccessfulTransaction($transaction, json_decode($transaction->response, true));
            }

            // Perform a fresh inquiry based on payment method
            $inquiryResponse = null;
            $isFinal = false;

            if ($transaction->payment_method === 'INNBUCKS') {
                $request = [
                    'reference' => $transaction->trace,
                    'code' => $transaction->reference,
                ];
                $inquiryResponse = $this->innbucksService->inquirePaymentRequest($request);
                $isFinal = $inquiryResponse['status'] === 'Claimed';

            } elseif ($transaction->payment_method === 'ECOCASH') {
                $inquiryResponse = $this->ecocashService->inquirePaymentRequest($transaction->pan, $transaction->reference);
                $isFinal = $inquiryResponse['transactionOperationStatus'] === 'COMPLETED' ||
                           $inquiryResponse['transactionOperationStatus'] === 'FAILED';
            } elseif ($transaction->payment_method === 'VISA_MASTER') {
                // For iVeri, check the payment status
                $inquiryResponse = $this->iveriService->checkPaymentStatus($transaction->reference);

                // Check if the payment has been finalized
                $isFinal = isset($inquiryResponse['success']) &&
                          ($inquiryResponse['success'] === true || $inquiryResponse['error'] === true);

                // If payment was successful but transaction not yet updated
                if ($inquiryResponse['success'] === true && $transaction->status !== 'COMPLETED') {
                    $transaction->update([
                        'status' => 'COMPLETED',
                        'response' => json_encode($inquiryResponse),
                        'response_code' => '000',
                        'type' => 'PAYMENT'
                    ]);
                }

                // If payment failed but transaction not yet updated
                if ($inquiryResponse['error'] === true && $transaction->status !== 'FAILED') {
                    $transaction->update([
                        'status' => 'FAILED',
                        'response' => json_encode($inquiryResponse),
                        'response_code' => $inquiryResponse['data']['StatusCode'] ?? '001',
                        'error_message' => $inquiryResponse['message'] ?? 'Payment failed',
                        'type' => 'PAYMENT'
                    ]);
                }
            } elseif ($transaction->payment_method === 'ZIMSWITCH') {
                // For ZimSwitch, transactions are handled via callbacks, not polling
                // If we reach this point, it means the transaction is still pending
                // ZimSwitch callbacks will directly finalize the transaction
                $isFinal = false;
                $inquiryResponse = json_decode($transaction->response, true);

                // Return a special response indicating no polling is needed
                return response()->json([
                    'success' => true,
                    'status' => 'PENDING',
                    'trace' => $trace,
                    'message' => 'ZimSwitch payment in progress. Complete the payment to continue.',
                    'shouldPoll' => false, // ZimSwitch doesn't use polling
                    'paymentMethod' => $transaction->payment_method,
                    'useCallback' => true // Indicate this payment uses callback mechanism
                ]);
            } elseif ($transaction->payment_method === 'OMARI') {
                // For Omari, first check the current transaction data
                $transactionData = json_decode($transaction->response, true);

                // Always query the payment status from Omari service
                $inquiryResponse = $this->omariService->inquirePaymentRequest($transaction->reference);

                // Check if the inquiry response indicates payment success or failure
                if (isset($inquiryResponse['status'])) {
                    if ($inquiryResponse['status'] === 'Success') {
                        // Payment completed successfully
                        $isFinal = true;
                    } else if ($inquiryResponse['status'] === 'Failed') {
                        // Payment failed
                        $isFinal = true;

                        // Update transaction with failure details
                        if ($transaction->status !== 'FAILED') {
                            $transaction->update([
                                'status' => 'FAILED',
                                'response_code' => $inquiryResponse['responseCode'] ?? '01',
                                'error_message' => $inquiryResponse['message'] ?? 'Payment failed',
                                'type' => 'PAYMENT'
                            ]);
                        }
                    } else {
                        // Status is something else (possibly still pending)
                        $isFinal = false;
                    }
                } else {
                    // No status in response, check if there's an error in the transaction data
                    if (isset($transactionData['error']) ||
                        (isset($transactionData['paymentResponse']) &&
                         isset($transactionData['paymentResponse']['error']) &&
                         $transactionData['paymentResponse']['error'] === true)) {

                        // We already have an error from a previous attempt
                        $errorMessage = $transactionData['message'] ??
                                        $transactionData['paymentResponse']['message'] ??
                                        'Payment failed';
                        $responseCode = $transactionData['paymentResponse']['responseCode'] ?? '01';

                        // Update transaction status if not already failed
                        if ($transaction->status !== 'FAILED') {
                            $transaction->update([
                                'status' => 'FAILED',
                                'response_code' => $responseCode,
                                'error_message' => $errorMessage,
                                'type' => 'PAYMENT'
                            ]);
                        }

                        $inquiryResponse = $transactionData['paymentResponse'] ?? $transactionData;
                        $isFinal = true;
                    } else if (isset($transactionData['otp']) && isset($transactionData['otpReference'])) {
                        // OTP was submitted but payment is still being processed
                        $isFinal = false;
                    } else {
                        // Still waiting for OTP from user
                        $isFinal = false;
                        $inquiryResponse = $transactionData;

                        // Return a response indicating we need OTP
                        return response()->json([
                            'success' => true,
                            'status' => 'PENDING',
                            'trace' => $trace,
                            'message' => 'Waiting for OTP verification.',
                            'shouldPoll' => true,
                            'requiresOtp' => true,
                            'paymentMethod' => $transaction->payment_method
                        ]);
                    }
                }
            }

            // If we have a final status, update the transaction
            if ($isFinal) {
                if (($transaction->payment_method === 'ECOCASH' &&
                    $inquiryResponse['transactionOperationStatus'] === 'FAILED') ||
                ($transaction->payment_method === 'OMARI' &&
                    (isset($inquiryResponse['error']) && $inquiryResponse['error'] === true ||
                     isset($inquiryResponse['status']) && $inquiryResponse['status'] === 'Failed'))) {
                    return $this->handleFailedTransaction(
                        $transaction,
                        $inquiryResponse['responseMessage'] ?? $inquiryResponse['message'] ?? 'Transaction failed.',
                        '01'
                    );
                }

                return $this->finalizeSuccessfulTransaction($transaction, $inquiryResponse);
            }

            // Return current status
            return response()->json([
                'success' => true,
                'status' => 'PENDING',
                'trace' => $trace,
                'message' => 'Transaction is still being processed.',
                'shouldPoll' => true,
                'paymentMethod' => $transaction->payment_method
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function finalizeSuccessfulTransaction(Transaction $originalTransaction, array $paymentResponse)
    {
        DB::beginTransaction();

        try {
            // Create a new transaction with completed status
            $newTransaction = Transaction::create([
                'type' => 'PAYMENT',
                'pan' => $originalTransaction->pan,
                'expiry_date' => $originalTransaction->expiry_date,
                'trace' => $originalTransaction->trace,
                'reference' => $originalTransaction->reference,
                'currency' => $originalTransaction->currency,
                'amount' => number_format((float) $originalTransaction->amount + (float) $originalTransaction->charge, 2, '.', ''),
                'charge' => $originalTransaction->charge,
                'status' => 'COMPLETED',
                'payment_method' => $originalTransaction->payment_method,
                'numeric_amount' => $originalTransaction->numeric_amount,
                'response_code' => '00',
                'request' => $originalTransaction->request,
                'response' => json_encode($paymentResponse),
                'merchant_uid' => $originalTransaction->merchant_uid,
                'user_name' => $originalTransaction->user_name,
                'user_id' => $originalTransaction->user_id,
                'user_type' => $originalTransaction->user_type,
                'credit_reference' => $paymentResponse['ecocashReference'] ?? $paymentResponse['stan'] ?? $paymentResponse['paymentReference'] ?? $paymentResponse['zimswitchReference'] ?? $paymentResponse['transactionId'] ?? null,
                'debit_reference' => $paymentResponse['debitReference'] ?? Str::uuid()->toString(),
                'parent_transaction_id' => $originalTransaction->id
            ]);

            // Update the original transaction to mark it as processed
            $originalTransaction->update([
                'status' => 'PROCESSED'
            ]);

            // Get the user associated with the transaction
            $user = $this->getUserFromTransaction($originalTransaction);
            if (!$user) {
                throw new \Exception('User not found.');
            }

            // Handle system and merchant charges as separate records
            $this->createChargeRecords($originalTransaction, $user);

            // Send webhook notification if merchant has web service URL configured
            if ($user->role === 'MERCHANT' && !empty($user->web_service_url)) {
                $this->sendPostRequest($user->web_service_url, [
                    'transaction' => $newTransaction->toArray(),
                    'status' => 'COMPLETED',
                    'timestamp' => now()->toIso8601String(),
                    'trace' => $originalTransaction->trace,
                    'amount' => $newTransaction->amount,
                    'currency' => $newTransaction->currency,
                    'payment_method' => $newTransaction->payment_method,
                    'reference' => $newTransaction->reference,
                    'merchant_uid' => $newTransaction->merchant_uid
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $paymentResponse,
                'trace' => $originalTransaction->trace,
                'status' => 'COMPLETED',
                'responseCode' => '00',
                'returnUrl' => $user->return_url,
                'responseMessage' => 'Transaction completed successfully.',
                'message' => 'Transaction Paid successfully.',
                'transaction' => $newTransaction
            ]);

            // send a post request to the merchant's web service URL
            // $this->sendPostRequest($user->web_service_url, $newTransaction);


        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleFailedTransaction(
                $originalTransaction,
                'Failed to finalize transaction: ' . $e->getMessage(),
                '01'
            );
        }
    }

    protected function createChargeRecords(Transaction $transaction, $user)
    {
        bcscale(2);
        // System charge logic - always create for both user and merchant types
        $systemCharge = SystemCharge::active()
            ->where('user_email', $transaction->user_type === 'U' ? $user->email : $this->getParentUserEmail($user))
            ->where('currency', $transaction->currency)
            ->where('min_threshold', '<=', $transaction->amount)
            ->where('max_threshold', '>=', $transaction->amount)
            ->first();

        if (!$systemCharge) {
            throw new \Exception('No applicable system charge found.');
        }

        $calculatedSystemCharge = $this->calculateCharge(
            $systemCharge->charge_type,
            $systemCharge->value,
            $transaction->amount
        );

        // Create system charge transaction
        Transaction::create([
            'type' => 'SYSTEM_CHARGE',
            'pan' => $transaction->pan,
            'trace' => $transaction->trace,
            'currency' => $transaction->currency,
            'amount' => bcdiv($calculatedSystemCharge, '1', 2),
            'status' => 'COMPLETED',
            'response_code' => '00',
            'payment_method' => $transaction->payment_method,
            'user_name' => $transaction->user_name,
            'user_id' => $transaction->user_id,
            'user_type' => $transaction->user_type,
            'merchant_uid' => $transaction->merchant_uid,
            'parent_transaction_id' => $transaction->id,
        ]);

        // Merchant charge logic - only for merchant users
        if ($transaction->user_type === 'M') {
            $merchantCharge = Charge::active()
                ->where('merchant_user_name', $user->email)
                ->where('charge_source', 'MERCHANT')
                ->where('currency', $transaction->currency)
                ->where('min_threshold', '<=', $transaction->amount)
                ->where('max_threshold', '>=', $transaction->amount)
                ->first();

            $calculatedMerchantCharge = $merchantCharge
                ? $this->calculateCharge(
                    $merchantCharge->charge_type,
                    $merchantCharge->value,
                    $transaction->amount
                  )
                : 0;

            // Create merchant charge transaction
            Transaction::create([
                'type' => 'MERCHANT_CHARGE',
                'pan' => $transaction->pan,
                'trace' => $transaction->trace,
                'currency' => $transaction->currency,
                'amount' =>  bcdiv($calculatedMerchantCharge, '1', 2),
                'status' => 'COMPLETED',
                'response_code' => '00',
                'payment_method' => $transaction->payment_method,
                'user_name' => $transaction->user_name,
                'user_id' => $transaction->user_id,
                'user_type' => $transaction->user_type,
                'merchant_uid' => $transaction->merchant_uid,
                'parent_transaction_id' => $transaction->id,
            ]);

            // Update original transaction with merchant charge amount
            $transaction->update(['merchant_charge' => $calculatedMerchantCharge]);
        }
    }

    protected function calculateCharge($chargeType, $value, $amount)
    {
        if ($chargeType === 'FLAT') {
            return $value;
        } elseif ($chargeType === 'PERCENTAGE') {
            return $amount * ($value / 100);
        }
        throw new \Exception('Invalid charge type.');


    }

    protected function getUserFromTransaction(Transaction $transaction)
    {
        if ($transaction->user_type === 'U') {
            return User::where('email', $transaction->user_name)->first();
        }

        $merchant = Merchant::where('user_id', $transaction->user_id)->first();
        return $merchant ? User::where('id', $merchant->user_id)->first() : null;
    }

    protected function getParentUserEmail($user)
    {
        $parentUser = User::find($user->primary_user);
        return $parentUser ? $parentUser->email : null;
    }

    protected function isTransactionCancelled($trace)
    {
        return Transaction::where('trace', $trace)
                ->where('type', 'CONFIRM')
                ->where('status', 'CANCELLED')
                ->exists();
    }

    protected function handleCancelledTransaction(Transaction $transaction)
    {
        $transaction->update([
            'status' => 'CANCELLED',
            'response_code' => '02',
            'error_message' => 'Transaction cancelled.',
            'type' => 'PAYMENT'
        ]);

        $user = $this->getUserFromTransaction($transaction);

        return response()->json([
            'success' => false,
            'status' => 'CANCELLED',
            'responseCode' => '02',
            'trace' => $transaction->trace,
            'returnUrl' => $user->return_url ?? null,
            'responseMessage' => 'Transaction cancelled.',
        ], 200);
    }

    protected function handleFailedTransaction(Transaction $transaction, $message, $responseCode, $errorMessage = null)
    {
        $transaction->update([
            'status' => 'FAILED',
            'response_code' => $responseCode,
            'error_message' => $errorMessage ?? $message,
            'type' => 'PAYMENT'
        ]);

        $user = $this->getUserFromTransaction($transaction);

        return response()->json([
            'success' => false,
            'status' => 'FAILED',
            'responseCode' => $responseCode,
            'trace' => $transaction->trace,
            'returnUrl' => $user->return_url ?? null,
            'responseMessage' => $message,
        ], 200);
    }

    protected function transactionErrorResponse($message, $trace, $responseCode, $statusCode, $returnUrl = null)
    {
        return response()->json([
            'success' => false,
            'status' => 'FAILED',
            'responseCode' => $responseCode,
            'trace' => $trace,
            'returnUrl' => $returnUrl,
            'responseMessage' => $message,
        ], $statusCode);
    }

    public function cancelTransaction(Request $request)
    {
        $trace = $request->trace;

        DB::beginTransaction();
        try {
            $transaction = Transaction::where('trace', $trace)
                            ->whereIn('type', ['CONFIRM', 'PAYMENT'])
                            ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found.',
                ], 404);
            }

            // Update the transaction status to 'CANCELLED'
            $transaction->update([
                'status' => 'CANCELLED',
                'response_code' => '02',
                'error_message' => 'Transaction cancelled by user request'
            ]);

            // If this is a CONFIRM transaction, also cancel any related PAYMENT transaction
            if ($transaction->type === 'CONFIRM') {
                Transaction::where('parent_transaction_id', $transaction->id)
                    ->update([
                        'status' => 'CANCELLED',
                        'response_code' => '02',
                        'error_message' => 'Parent transaction cancelled'
                    ]);
            }

            DB::commit();

            $user = $this->getUserFromTransaction($transaction);

            return response()->json([
                'success' => true,
                'status' => 'CANCELLED',
                'message' => 'Transaction cancelled successfully.',
                'trace' => $trace,
                'returnUrl' => $user->return_url ?? null,
                'transaction' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function generateUserTransactionToken(Request $request)
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can generate transaction tokens.'], 403);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        // Extract request data
        $amount = $request->amount;
        $currency = $request->currency;
        $calculatedSystemCharge = 0;

        // Retrieve the system charge based on the currency and thresholds
        $systemCharge = SystemCharge::active()
            ->where('user_email', $authenticatedUser->email)
            ->where('currency', $currency)
            ->where('min_threshold', '<=', $amount)
            ->where('max_threshold', '>=', $amount)
            ->first();

        if (!$systemCharge) {
            return response()->json(['message' => 'No applicable system charge found for this user and the given amount and currency.'], 404);
        }

        // Calculate the system charge based on the charge_type
        if ($systemCharge->charge_type === 'FLAT') {
            $calculatedSystemCharge = $systemCharge->value;
        } elseif ($systemCharge->charge_type === 'PERCENTAGE') {
            $calculatedSystemCharge = $amount * ($systemCharge->value / 100);
        } else {
            return response()->json(['message' => 'Invalid charge type.'], 500);
        }

        $totalCharge = $calculatedSystemCharge;
        $totalAmount = $amount + $totalCharge;

        // Generate the token payload
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'charge' => number_format($totalCharge, 2),
            'totalAmount' => number_format($totalAmount, 2),
            'name' => $authenticatedUser->company_name,
            'description' => $authenticatedUser->description,
            'user' => $authenticatedUser->email,
            'sub' => 'Payment',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(1)->timestamp, // Token expires in 1 minute
        ];

        $secretKey = env('JWT_SECRET');
        if (empty($secretKey) || !is_string($secretKey)) {
            throw new \RuntimeException('JWT_SECRET is not set or invalid!');
        }

        // Manually encode the token using the user's `user_secret`
        $token = JWT::encode($payload, $secretKey, 'HS256');

        // Generate the checkout URL
        $checkoutUrl = url("/check-out?token={$token}&type=u");

        // Return the response
        return response()->json([
            'timestamp' => now()->toDateTimeString(),
            'statusCode' => 200,
            'status' => 'OK',
            'message' => 'Successfully created a user payment token',
            'path' => '/api/v1/transaction/u/generate',
            'requestMethod' => 'POST',
            'data' => [
                'token' => $token,
                'url' => $checkoutUrl,
            ],
        ], 200);
    }

    public function getUserTransactions(Request $request)
    {
        $user = JWTAuth::user();

        $query = Transaction::where('user_id', $user->id)
            ->whereIn('type', ['CONFIRM', 'PAYMENT'])
            ->orderBy('created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ]);
    }

    public function getMerchantTransactions($merchant_id)
    {
        $user = JWTAuth::user();

        // Verify merchant exists
        $merchant = User::find($merchant_id);
        if (!$merchant || $merchant->role !== 'MERCHANT') {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        }

        // Check if user has permission to view these transactions
        if ($user->role === 'ADMIN') {
            // Admin can only view transactions of merchants where they are the primary user
            if ($merchant->primary_user !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You can only view transactions for merchants under your company.'
                ], 403);
            }
        } elseif ($user->role === 'MERCHANT') {
            // Merchant can only view their own transactions
            if ($user->id !== $merchant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You can only view your own transactions.'
                ], 403);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = Transaction::where('user_id', $merchant_id)
            ->where('user_type', 'M')
            ->whereIn('type', ['CONFIRM', 'PAYMENT'])
            ->orderBy('created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ]);
    }

    public function getMerchantCharges($merchant_id)
    {
        $user = JWTAuth::user();

        // Verify merchant exists
        $merchant = User::find($merchant_id);
        if (!$merchant || $merchant->role !== 'MERCHANT') {
            return response()->json([
                'success' => false,
                'message' => 'Merchant not found'
            ], 404);
        }

        // Check if user has permission to view these charges
        if ($user->role === 'ADMIN') {
            // Admin can only view charges of merchants where they are the primary user
            if ($merchant->primary_user !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You can only view charges for merchants under your company.'
                ], 403);
            }
        } elseif ($user->role === 'MERCHANT') {
            // Merchant can only view their own charges
            if ($user->id !== $merchant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You can only view your own charges.'
                ], 403);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = Transaction::where('user_id', $merchant_id)
            ->where('user_type', 'M')
            ->where('type', 'MERCHANT_CHARGE')
            ->orderBy('created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ]);
    }

    public function getDashboardStats(Request $request)
    {
        $user = JWTAuth::user();

        try {
            // Calculate total volume
            $totalVolume = Transaction::where('type', 'PAYMENT')
                ->where('status', 'COMPLETED')
                ->sum('amount');

            // Count total transactions
            $totalTransactions = Transaction::where('type', 'PAYMENT')
                ->where('status', 'COMPLETED')
                ->count();

            // Get system charges total
            $systemCharges = Transaction::where('type', 'SYSTEM_CHARGE')
                ->where('status', 'COMPLETED')
                ->sum('amount');

            // Get merchant charges total
            $merchantCharges = Transaction::where('type', 'MERCHANT_CHARGE')
                ->where('status', 'COMPLETED')
                ->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'totalVolume' => $totalVolume,
                    'totalTransactions' => $totalTransactions,
                    'systemCharges' => $systemCharges,
                    'merchantCharges' => $merchantCharges
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard stats'
            ], 500);
        }
    }

    public function getRecentTransactions(Request $request)
    {
        $user = JWTAuth::user();
        $search = $request->get('search', '');
        $perPage = $request->get('per_page', 10);

        try {
            $query = Transaction::whereIn('type', ['PAYMENT'])
                ->where(function($q) use ($search) {
                    if (!empty($search)) {
                        $search = strtolower($search);
                        $q->where('id', 'LIKE', "%{$search}%")
                          ->orWhere('user_name', 'LIKE', "%{$search}%")
                          ->orWhereRaw('LOWER(CAST(amount AS CHAR)) LIKE ?', ["%{$search}%"])
                          ->orWhere('status', 'LIKE', "%{$search}%")
                          ->orWhere('currency', 'LIKE', "%{$search}%");
                    }
                });

            $query->orderBy('created_at', 'desc');

            return response()->json([
                'success' => true,
                'data' => $query->paginate($perPage)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching recent transactions'
            ], 500);
        }
    }

    public function getAllTransactions(Request $request)
    {
        $user = JWTAuth::user();

        // Ensure only ADMIN users can access this endpoint
        if ($user->role !== 'ADMIN' && $user->role !== 'SUPER') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only ADMIN or SUPER users can view all transactions.'
            ], 403);
        }

        $search = $request->get('search', '');
        $perPage = $request->get('per_page', 10);
        $status = $request->get('status', '');
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');
        $currency = $request->get('currency', '');
        $countByStatus = $request->get('count_by_status', false);

        try {
            // If we just need to count transactions by status
            if ($countByStatus) {
                $counts = Transaction::whereIn('type', ['PAYMENT'])
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();

                return response()->json([
                    'success' => true,
                    'data' => $counts
                ]);
            }

            // Build the query with filters
            $query = Transaction::whereIn('type', ['PAYMENT']);

            // Apply search filter
            if (!empty($search)) {
                $search = strtolower($search);
                $query->where(function($q) use ($search) {
                    $q->where('id', 'LIKE', "%{$search}%")
                      ->orWhere('user_name', 'LIKE', "%{$search}%")
                      ->orWhereRaw('LOWER(CAST(amount AS CHAR)) LIKE ?', ["%{$search}%"])
                      ->orWhere('status', 'LIKE', "%{$search}%")
                      ->orWhere('currency', 'LIKE', "%{$search}%");
                });
            }

            // Apply status filter
            if (!empty($status)) {
                $query->where('status', $status);
            }

            // Apply date range filter
            if (!empty($startDate)) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if (!empty($endDate)) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Apply currency filter
            if (!empty($currency)) {
                $query->where('currency', $currency);
            }

            // Order by created_at desc
            $query->orderBy('created_at', 'desc');

            // Return paginated results
            return response()->json([
                'success' => true,
                'data' => $query->paginate($perPage)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateReceipt(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required',
                'date' => 'required',
                'amount' => 'required',
                'status' => 'required',
                'type' => 'required',
                'customer' => 'required',
                'reference' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();

            // Get currency symbol
            $currencySymbol = $this->getCurrencySymbol($data['currency'] ?? 'USD');

            // Format amount with currency
            $amount = $data['amount'];
            if (is_numeric($amount)) {
                $data['amount'] = $currencySymbol . ' ' . number_format($amount, 2);
            }

            // If charge exists, format it and calculate total
            if (isset($data['charge']) && is_numeric($data['charge'])) {
                $charge = $data['charge'];
                $data['charge'] = $currencySymbol . ' ' . number_format($charge, 2);
                $data['total_amount'] = $currencySymbol . ' ' . number_format($amount + $charge, 2);
            }

            // Generate PDF using DomPDF with standalone view in PDF middleware
            $pdf = Pdf::loadView('pdf.receipt', $data)
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'isFontSubsettingEnabled' => true,
                    'defaultFont' => 'sans-serif'
                ]);

            // Set paper size and orientation
            $pdf->setPaper('a4', 'portrait');

            // Generate a filename
            $filename = 'receipt_' . $data['transaction_id'] . '_' . date('YmdHis') . '.pdf';

            // Set paper size and margins
            $pdf->setPaper('a4');
            $pdf->setOption('margin-top', 10);
            $pdf->setOption('margin-right', 10);
            $pdf->setOption('margin-bottom', 10);
            $pdf->setOption('margin-left', 10);

            // Return the PDF as a download
            return $pdf->stream($filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTransactionDetails($id)
    {
        $user = JWTAuth::user();

        try {
            // Find the transaction
            $transaction = Transaction::findOrFail($id);

            // Check if user has permission to view this transaction
            if ($user->role === 'ADMIN' || $user->role === 'SUPER') {
                // Admin can view all transactions
            } elseif ($user->role === 'MERCHANT') {
                // Merchant can only view their own transactions
                if ($transaction->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. You can only view your own transactions.'
                    ], 403);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Get related transactions (charges)
            $relatedTransactions = Transaction::where('parent_transaction_id', $transaction->id)->get();

            // Add related transactions to the response
            $transaction->related_transactions = $relatedTransactions;

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching transaction details: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function sendPostRequest($url, $data)
    {
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 30,
                'connect_timeout' => 30
            ]);

            $response = $client->post($url, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody(), true);

            Log::info('Merchant webhook notification sent', [
                'url' => $url,
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status_code' => $statusCode,
                'response' => $responseBody
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send merchant webhook notification', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function getCurrencySymbol($currency)
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'ZWL' => 'ZWL',
            'ZAR' => 'R'
        ];

        return $symbols[$currency] ?? $currency;
    }

    public function processOmariOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trace' => 'required|string',
            'otp' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = Transaction::where('trace', $request->trace)
                            ->where('payment_method', 'OMARI')
                            ->whereIn('type', ['CONFIRM'])
                            ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found or invalid payment method.'
                ], 404);
            }

            // Get the transaction data
            $transactionData = json_decode($transaction->response, true);

            // Process the payment with OTP
            $paymentResponse = $this->omariService->processPayment(
                $transactionData['msisdn'] ?? $transaction->pan,
                $transaction->reference,
                $request->otp
            );

            // Check if payment has an error
            if (isset($paymentResponse['error']) && $paymentResponse['error'] === true) {
                // Update transaction with the error response
                $transaction->update([
                    'response' => json_encode(array_merge($transactionData, [
                        'otp' => $request->otp,
                        'paymentResponse' => $paymentResponse,
                        'error' => $paymentResponse['message'] ?? 'Payment failed'
                    ])),
                    'status' => 'FAILED',
                    'response_code' => $paymentResponse['responseCode'] ?? '01',
                    'error_message' => $paymentResponse['message'] ?? 'Payment failed'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $paymentResponse['message'] ?? 'Payment failed',
                    'responseCode' => $paymentResponse['responseCode'] ?? '01',
                    'shouldPoll' => false
                ]);
            }

            // Update transaction with OTP info for successful payment
            $transaction->update([
                'response' => json_encode(array_merge($transactionData, [
                    'otp' => $request->otp,
                    'otpReference' => $paymentResponse['paymentReference'] ?? null,
                    'paymentResponse' => $paymentResponse
                ]))
            ]);

            return response()->json([
                'success' => true,
                'message' => $paymentResponse['message'] ?? 'OTP processed successfully',
                'data' => $paymentResponse,
                'shouldPoll' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function generateMerchantTransactionToken(Request $request)
    {
        // Ensure only MERCHANT users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'MERCHANT') {
            return response()->json(['message' => 'Unauthorized. Only MERCHANT users can generate transaction tokens.'], 403);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        // Extract request data
        $amount = $request->amount;
        $currency = $request->currency;
        $calculatedSystemCharge = 0;

        // Find the user associated with the authenticated user
        $merchant = User::where('id', $authenticatedUser->id)->first();
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found.'], 404);
        }

        // Find the parent user
        $parentUser = User::where('id', $merchant->primary_user)->first();
        if (!$parentUser) {
            return response()->json(['message' => 'Parent Account not found.'], 404);
        }

        // System charge logic
        $systemCharge = SystemCharge::active()
            ->where('user_email', $parentUser->email)
            ->where('currency', $currency)
            ->where('min_threshold', '<=', $amount)
            ->where('max_threshold', '>=', $amount)
            ->first();

        if (!$systemCharge) {
            return response()->json(['message' => 'No applicable system charge found for this user and the given amount and currency.'], 404);
        }

        // Calculate the system charge based on the charge_type
        if ($systemCharge->charge_type === 'FLAT') {
            $calculatedSystemCharge = $systemCharge->value;
        } elseif ($systemCharge->charge_type === 'PERCENTAGE') {
            $calculatedSystemCharge = $amount * ($systemCharge->value / 100);
        } else {
            return response()->json(['message' => 'Invalid charge type.'], 500);
        }

        // Merchant charge logic
        $merchantCharge = Charge::active()
            ->where('merchant_user_name', $authenticatedUser->email)
            ->where('charge_source', 'MERCHANT')
            ->where('currency', $currency)
            ->where('min_threshold', '<=', $amount)
            ->where('max_threshold', '>=', $amount)
            ->first();

        if ($merchantCharge) {
            if ($merchantCharge->charge_type === 'FLAT') {
                $calculatedMerchantCharge = $merchantCharge->value;
            } elseif ($merchantCharge->charge_type === 'PERCENTAGE') {
                $calculatedMerchantCharge = $amount * ($merchantCharge->value / 100);
            } else {
                return response()->json(['message' => 'Invalid charge type.'], 500);
            }
        } else {
            $calculatedMerchantCharge = 0;
        }

        $totalCharge = $calculatedMerchantCharge + $calculatedSystemCharge;
        $totalAmount = $amount + $totalCharge;

        $merchantUser = Merchant::where('user_id', $authenticatedUser->id)->first();

        // Generate the token payload
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'charge' => number_format($totalCharge, 2),
            'totalAmount' => number_format($totalAmount, 2),
            'name' => $merchantUser->merchant_name,
            'description' => $merchantUser->merchant_description,
            'user' => $authenticatedUser->email,
            'sub' => 'MerchantPayment',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(1)->timestamp,
        ];

        // Manually encode the token using the merchant's `merchant_secret`
        $token = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        // Generate the checkout URL
        $checkoutUrl = url("/check-out?token={$token}&type=m");

        // Return the response
        return response()->json([
            'timestamp' => now()->toDateTimeString(),
            'statusCode' => 200,
            'status' => 'OK',
            'message' => 'Successfully created a merchant payment token',
            'path' => '/api/v1/transaction/u/generate',
            'requestMethod' => 'POST',
            'data' => [
                'token' => $token,
                'url' => $checkoutUrl,
            ],
        ], 200);
    }

    /**
     * Handle payment callback from Zimswitch
     * This method is called when the user is redirected back from the payment gateway
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handlePaymentCallback(Request $request)
    {
        // Get the checkout ID from the request (provided by EFTPay in the redirect)
        $checkoutId = $request->query('id');
        $resourcePath = $request->query('resourcePath');

        if (!$checkoutId) {
            return response()->view('payment.error', [
                'message' => 'Payment checkout ID is missing.'
            ]);
        }

        try {
            // Use the checkout ID to get the payment status
            $paymentStatus = $this->zimswitchService->getPaymentStatus($checkoutId);

            // Find the transaction associated with this payment
            // Note: The merchant transaction ID is stored in our trace field
            $transaction = Transaction::where('payment_method', 'ZIMSWITCH')
                            ->whereIn('type', ['CONFIRM'])
                            ->orderBy('created_at', 'desc')
                            ->first();

            if (!$transaction) {
                return response()->view('payment.error', [
                    'message' => 'Transaction not found.'
                ]);
            }

            // Update transaction with the payment status
            if ($paymentStatus['success']) {
                // Payment was successful, finalize the transaction properly
                $paymentResponseData = [
                    'transactionId' => $paymentStatus['transactionId'] ?? null,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'resultCode' => $paymentStatus['responseCode'] ?? '000.000.000',
                    'resultDescription' => $paymentStatus['message'] ?? 'Transaction completed successfully',
                    'paymentBrand' => 'ZIMSWITCH',
                    'zimswitchReference' => $paymentStatus['transactionId'] ?? null,
                    'paymentReference' => $paymentStatus['transactionId'] ?? null,
                    'fullPaymentStatus' => $paymentStatus['responseData'] ?? $paymentStatus
                ];

                $this->finalizeSuccessfulTransaction($transaction, $paymentResponseData);

                // Redirect to success page or merchant return URL
                return redirect()->to('/payment/success?reference=' . $transaction->reference);
            } else {
                // Payment failed, update transaction to failed
                $transaction->update([
                    'status' => 'FAILED',
                    'response_code' => $paymentStatus['responseCode'] ?? '01',
                    'error_message' => $paymentStatus['message'] ?? 'Payment failed',
                    'response' => json_encode($paymentStatus['responseData'])
                ]);

                // Redirect to failure page or merchant return URL
                return redirect()->to('/payment/failed?reference=' . $transaction->reference);
            }
        } catch (\Exception $e) {
            // Log the error
            Log::error('Zimswitch payment callback error: ' . $e->getMessage());

            return response()->view('payment.error', [
                'message' => 'An error occurred while processing your payment: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Check Zimswitch payment status using resource path
     * Called by Vue.js component after payment completion
     */
    public function checkZimswitchPaymentStatus(Request $request)
    {
        try {
            $request->validate([
                'resourcePath' => 'required|string',
                'trace' => 'required|string'
            ]);

            $resourcePath = $request->input('resourcePath');
            $trace = $request->input('trace');

            // Use the Zimswitch service to check payment status
            $paymentStatus = $this->zimswitchService->checkPaymentStatus($resourcePath);

            if ($paymentStatus['success']) {
                // Find the transaction by trace (ensure it's a ZIMSWITCH CONFIRM transaction)
                $transaction = Transaction::where('trace', $trace)
                                        ->where('payment_method', 'ZIMSWITCH')
                                        ->whereIn('type', ['CONFIRM'])
                                        ->first();

                if ($transaction) {
                    // Prepare payment response data for finalization
                    $paymentResponseData = [
                        'transactionId' => $paymentStatus['transactionId'] ?? null,
                        'amount' => $paymentStatus['amount'],
                        'currency' => $paymentStatus['currency'],
                        'resultCode' => $paymentStatus['resultCode'] ?? '000.000.000',
                        'resultDescription' => $paymentStatus['resultDescription'] ?? 'Transaction completed successfully',
                        'paymentBrand' => 'ZIMSWITCH',
                        'zimswitchReference' => $paymentStatus['transactionId'] ?? null,
                        'paymentReference' => $paymentStatus['transactionId'] ?? null,
                        'fullPaymentStatus' => $paymentStatus
                    ];

                    Log::info('Zimswitch payment completed successfully, finalizing transaction', [
                        'trace' => $trace,
                        'transaction_id' => $transaction->id,
                        'amount' => $paymentStatus['amount'],
                        'currency' => $paymentStatus['currency']
                    ]);

                    // Use the same finalization process as other payment methods
                    $finalizationResponse = $this->finalizeSuccessfulTransaction($transaction, $paymentResponseData);

                    // Extract data from the finalization response
                    $responseData = json_decode($finalizationResponse->getContent(), true);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment completed and finalized successfully',
                        'transaction' => $responseData['transaction'] ?? [
                            'id' => $transaction->id,
                            'trace' => $transaction->trace,
                            'amount' => $transaction->amount,
                            'currency' => $transaction->currency,
                            'status' => 'COMPLETED',
                            'payment_method' => $transaction->payment_method
                        ],
                        'finalized' => true
                    ]);
                } else {
                    Log::warning('Transaction not found for Zimswitch payment', [
                        'trace' => $trace,
                        'resourcePath' => $resourcePath
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment completed but transaction not found',
                        'paymentStatus' => $paymentStatus
                    ]);
                }
            } else {
                // Payment failed - update transaction using proper failure handling
                $transaction = Transaction::where('trace', $trace)
                                        ->where('payment_method', 'ZIMSWITCH')
                                        ->whereIn('type', ['CONFIRM'])
                                        ->first();

                if ($transaction) {
                    Log::warning('Zimswitch payment failed, updating transaction', [
                        'trace' => $trace,
                        'transaction_id' => $transaction->id,
                        'resourcePath' => $resourcePath,
                        'error' => $paymentStatus['message']
                    ]);

                    // Use proper failure handling method
                    $failureResponse = $this->handleFailedTransaction(
                        $transaction,
                        $paymentStatus['message'] ?? 'Payment failed',
                        $paymentStatus['resultCode'] ?? '01',
                        json_encode($paymentStatus)
                    );

                    // Extract data from the failure response
                    $responseData = json_decode($failureResponse->getContent(), true);

                    return response()->json([
                        'success' => false,
                        'message' => $paymentStatus['message'],
                        'transaction' => $responseData['transaction'] ?? null,
                        'error' => true
                    ]);
                } else {
                    Log::warning('Zimswitch payment failed but transaction not found', [
                        'trace' => $trace,
                        'resourcePath' => $resourcePath,
                        'error' => $paymentStatus['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $paymentStatus['message'],
                        'error' => true
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Zimswitch payment status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
                'error' => true
            ], 500);
        }
    }

    /**
     * Test Zimswitch checkout creation directly
     * This is a debug endpoint to test checkout creation without full transaction flow
     */
    public function testZimswitchCheckout(Request $request)
    {
        try {
            $amount = $request->input('amount', '1.00');
            $currency = $request->input('currency', 'USD');

            // Test checkout creation directly
            $result = $this->zimswitchService->prepareCheckout([
                'amount' => $amount,
                'currency' => $currency,
                'trace' => 'TEST_' . time()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test checkout creation completed',
                'result' => $result,
                'test_script_url' => isset($result['authConfig']) && isset($result['checkoutId'])
                    ? 'https://' . $result['authConfig']['checkoutUrl'] . $result['checkoutId']
                    : 'N/A'
            ]);

        } catch (\Exception $e) {
            Log::error('Zimswitch test checkout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Test checkout failed: ' . $e->getMessage(),
                'error' => true
            ], 500);
        }
    }

    /**
     * Handle EFTPay callback URL and extract payment information
     * This handles the callback URL that EFTPay redirects to
     */
    public function handleEftPayCallback(Request $request)
    {
        try {
            $request->validate([
                'callbackUrl' => 'required|string|url',
                'trace' => 'required|string'
            ]);

            $callbackUrl = $request->input('callbackUrl');
            $trace = $request->input('trace');

            // Parse the callback URL to extract payment information
            $urlParts = parse_url($callbackUrl);
            parse_str($urlParts['query'] ?? '', $queryParams);

            Log::info('EFTPay callback URL received', [
                'callbackUrl' => $callbackUrl,
                'queryParams' => $queryParams,
                'trace' => $trace
            ]);

            // Extract key payment information from URL parameters
            $status = $queryParams['status'] ?? null;
            $uuid = $queryParams['uuid'] ?? null;
            $resultDescription = isset($queryParams['resultDetails.ExtendedDescription'])
                ? urldecode($queryParams['resultDetails.ExtendedDescription'])
                : null;

            // Determine if payment was successful using same pattern as working implementation
            $isSuccess = $status && preg_match("/^(000\.000\.|000\.100\.1|000\.[36])/", $status);

            if ($isSuccess) {
                // Find the transaction
                $transaction = Transaction::where('trace', $trace)
                                        ->where('payment_method', 'ZIMSWITCH')
                                        ->whereIn('type', ['CONFIRM'])
                                        ->first();

                if ($transaction) {
                    // Prepare payment response data for finalization
                    $paymentResponseData = [
                        'status' => $status,
                        'transactionId' => $uuid,
                        'uuid' => $uuid,
                        'description' => $resultDescription ?: 'Transaction completed successfully',
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency,
                        'paymentBrand' => 'ZIMSWITCH',
                        'resultCode' => $status,
                        'resultDescription' => $resultDescription,
                        'zimswitchReference' => $uuid,
                        'paymentReference' => $uuid,
                        'callbackParams' => $queryParams
                    ];

                    Log::info('EFTPay payment completed successfully, finalizing transaction', [
                        'trace' => $trace,
                        'transaction_id' => $transaction->id,
                        'status' => $status,
                        'uuid' => $uuid
                    ]);

                    // Use the same finalization process as other payment methods
                    $finalizationResponse = $this->finalizeSuccessfulTransaction($transaction, $paymentResponseData);

                    // Extract data from the finalization response
                    $responseData = json_decode($finalizationResponse->getContent(), true);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment completed and finalized successfully',
                        'paymentInfo' => [
                            'status' => $status,
                            'description' => $resultDescription ?: 'Transaction completed successfully',
                            'transactionId' => $uuid,
                            'trace' => $trace
                        ],
                        'transaction' => $responseData['transaction'] ?? null,
                        'finalized' => true
                    ]);
                } else {
                    Log::warning('EFTPay payment successful but transaction not found', [
                        'trace' => $trace,
                        'status' => $status,
                        'uuid' => $uuid
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment successful but transaction record not found',
                        'paymentInfo' => [
                            'status' => $status,
                            'description' => $resultDescription,
                            'transactionId' => $uuid,
                            'trace' => $trace
                        ]
                    ]);
                }
            } else {
                // Payment failed - update transaction using proper failure handling
                $transaction = Transaction::where('trace', $trace)
                                        ->where('payment_method', 'ZIMSWITCH')
                                        ->whereIn('type', ['CONFIRM'])
                                        ->first();

                if ($transaction) {
                    Log::warning('EFTPay payment failed, updating transaction', [
                        'trace' => $trace,
                        'transaction_id' => $transaction->id,
                        'status' => $status,
                        'description' => $resultDescription
                    ]);

                    // Use proper failure handling method
                    $failureResponse = $this->handleFailedTransaction(
                        $transaction,
                        $resultDescription ?: 'Payment failed',
                        $status ?: '01',
                        json_encode($queryParams)
                    );

                    // Extract data from the failure response
                    $responseData = json_decode($failureResponse->getContent(), true);

                    return response()->json([
                        'success' => false,
                        'message' => $resultDescription ?: 'Payment failed',
                        'paymentInfo' => [
                            'status' => $status,
                            'description' => $resultDescription ?: 'Payment failed',
                            'transactionId' => $uuid,
                            'trace' => $trace
                        ],
                        'transaction' => $responseData['transaction'] ?? null
                    ]);
                } else {
                    Log::warning('EFTPay payment failed but transaction not found', [
                        'trace' => $trace,
                        'status' => $status,
                        'description' => $resultDescription
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $resultDescription ?: 'Payment failed',
                        'paymentInfo' => [
                            'status' => $status,
                            'description' => $resultDescription ?: 'Payment failed',
                            'transactionId' => $uuid,
                            'trace' => $trace
                        ]
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('EFTPay callback handling error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment callback',
                'error' => true
            ], 500);
        }
    }
}
