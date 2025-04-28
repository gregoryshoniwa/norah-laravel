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
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    protected $innbucksService;
    protected $ecocashService;

    public function __construct(InnBucksPaymentService $innbucksService, EcoCashPaymentService $ecocashService)
    {
        $this->innbucksService = $innbucksService;
        $this->ecocashService = $ecocashService;
    }

    public function confirmTransaction(Request $request)
    {
        $paymentMethod = $request->input('paymentMethod');
        $response = null;

        try {
            if ($paymentMethod === 'INNBUCKS') {
                $response = $this->innbucksService->createPaymentRequest($request->all());
                $reference = $response['code'] ?? null;
            } elseif ($paymentMethod === 'ECOCASH') {
                $response = $this->ecocashService->createPaymentRequest($request->all());
                $reference = $response['referenceCode'] ?? null;
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
            $transaction->trace = Str::uuid()->toString();
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

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'Transaction confirmed successfully.',
                'trace' => $transaction->trace,
                'returnUrl' => $returnUrl,
                'transaction' => $transaction,
                'shouldPoll' => true,
                'pollInterval' => $paymentMethod === 'INNBUCKS' ? 30000 : 5000 // 30s or 5s
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
                $isFinal = $inquiryResponse['transactionOperationStatus'] === 'COMPLETE' ||
                           $inquiryResponse['transactionOperationStatus'] === 'FAILED';
            }

            // If we have a final status, update the transaction
            if ($isFinal) {
                if ($transaction->payment_method === 'ECOCASH' &&
                    $inquiryResponse['transactionOperationStatus'] === 'FAILED') {
                    return $this->handleFailedTransaction(
                        $transaction,
                        $inquiryResponse['responseMessage'] ?? 'Transaction failed.',
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
            // Update the original transaction
            $originalTransaction->update([
                'status' => 'COMPLETED',
                'response_code' => '00',
                'type' => 'PAYMENT',
                'response' => json_encode($paymentResponse),
                'credit_reference' => $paymentResponse['ecocashReference'] ?? $paymentResponse['stan'] ?? null,
                'debit_reference' => Str::uuid()->toString(),
            ]);

            // Get the user associated with the transaction
            $user = $this->getUserFromTransaction($originalTransaction);
            if (!$user) {
                throw new \Exception('User not found.');
            }

            // Handle system and merchant charges as separate records
            $this->createChargeRecords($originalTransaction, $user);

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
                'transaction' => $originalTransaction
            ]);

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
            'trace' => Str::uuid()->toString(),
            'currency' => $transaction->currency,
            'amount' => $calculatedSystemCharge,
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
                'trace' => Str::uuid()->toString(),
                'currency' => $transaction->currency,
                'amount' => $calculatedMerchantCharge,
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

            // In a real implementation, you would generate a PDF receipt here
            // For now, we'll just return a JSON response with the receipt data
            return response()->json([
                'success' => true,
                'data' => $request->all(),
                'message' => 'Receipt generated successfully'
            ]);
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
}
