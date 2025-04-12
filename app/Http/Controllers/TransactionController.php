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
            } elseif ($paymentMethod === 'ECOCASH') {
                $response = $this->ecocashService->createPaymentRequest($request->all());
            }

            $user = User::where('email', $request['user'])->first();

             // Save the confirmation to the database
            $transaction = new Transaction();
            $transaction->type = 'CONFIRM';
            $transaction->pan = $request->input('pan') ?? $request->input('phoneNumber');
            $transaction->expiry_date = $request->input('expiryDate');
            $transaction->trace = Str::uuid()->toString();
            $transaction->currency = $request->input('currency');
            $transaction->amount = number_format((float) $request->input('amount'), 2, '.', '');
            $transaction->charge = number_format((float) $request->input('charge'), 2, '.', '');
            $transaction->status = 'SUCCESS';
            $transaction->payment_method = $request->input('paymentMethod');
            $transaction->numeric_amount = number_format((float) $request->input('amount'), 2, '.', '');
            $transaction->response_code = '00';
            $transaction->merchant_uid = $request->input('merchantUid') ?? '';
            $transaction->user_name =  $user->email;
            $transaction->user_id = $user->id;
            $transaction->user_type = $user->role === 'ADMIN' ? 'U' : 'M';

            $transaction->save();


            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Exception $e) {
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
            'exp' => now()->addMinute()->timestamp, // Token expires in 1 hour
        ];

        // Manually encode the token using the user's `user_secret`
        $token = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

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

        // Find the merchant associated with the authenticated user
        $merchant = Merchant::where('user_id', $authenticatedUser->id)->first();
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found.'], 404);
        }

        // Retrieve the merchant's `merchant_secret`
        $merchantSecret = $merchant->merchant_secret;
        if (!$merchantSecret) {
            return response()->json(['message' => 'Merchant secret not found.'], 500);
        }

        //Sysem charge logic
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

        // Merchant charge logic
        $merchantCharge = Charge::active()
        ->where('merchant_user_name', $authenticatedUser->email)
        ->where('charge_source', 'MERCHANT')
        ->where('currency', $currency)
        ->where('min_threshold', '<=', $amount)
        ->where('max_threshold', '>=', $amount)
        ->first();

        if (!$merchantCharge) {
            $merchantCharge = 0;
        }

        // Calculate the system charge based on the charge_type
        if ($merchantCharge->charge_type === 'FLAT') {
            $calculatedMerchantCharge = $merchantCharge->value;
        } elseif ($merchantCharge->charge_type === 'PERCENTAGE') {
            $calculatedMerchantCharge = $amount * ($merchantCharge->value / 100);
        } else {
            return response()->json(['message' => 'Invalid charge type.'], 500);
        }

        $totalCharge = $calculatedMerchantCharge + $calculatedSystemCharge;
        $totalAmount = $amount + $totalCharge;

        // Generate the token payload
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'charge' => number_format($totalCharge, 2),
            'totalAmount' => number_format($totalAmount, 2),
            'name' => $merchant->merchant_name,
            'description' => $merchant->merchant_description,
            'user' => $authenticatedUser->email,
            'sub' => 'MerchantPayment',
            'iat' => now()->timestamp,
            'exp' => now()->addMinute()->timestamp, // Token expires in 1 minute
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
