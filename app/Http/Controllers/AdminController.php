<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Confirmation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;


class AdminController extends Controller
{
    public function createMerchantAccount(Request $request)
    {
        // Validate the request
        $request->validate([
            'merchantName' => 'required|string|max:255',
            'merchantAddress' => 'required|string|max:255',
            'merchantPhone' => 'required|string|max:255',
            'merchantEmail' => 'required|string|email|max:255|unique:merchants,merchant_email',
            'merchantCountry' => 'required|string|max:255',
            'merchantCity' => 'required|string|max:255',
            'merchantWebsite' => 'nullable|string|max:255',
            'merchantDescription' => 'nullable|string',
            'returnUrl' => 'nullable|string|max:255',
            'webServiceUrl' => 'nullable|string|max:255',
        ]);

        // Get the authenticated user
        $adminUser = JWTAuth::user();

        // Check if the user has the "ADMIN" role
        if ($adminUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only admins can create merchant accounts.'], 403);
        }

        // Generate merchant secret and UID
        $merchantSecret = Str::uuid()->toString();
        $merchantUid = Str::uuid()->toString();
        $merchantEmail = time() . '@norah.com';

        // Create a new user for the merchant
        $merchantUser = User::create([
            'first_name' => $request->merchantName,
            'last_name' => 'Merchant Transaction API',
            'email' => $merchantEmail, // Generate a unique email
            'password' => Hash::make($merchantSecret),
            'role' => 'MERCHANT',
            'is_activated' => true,
            'company_name' => $adminUser->company_name,
            'primary_user' => $adminUser->id,
        ]);

        // Create the merchant account
        $merchant = Merchant::create([
            'merchant_name' => $request->merchantName,
            'merchant_address' => $request->merchantAddress,
            'merchant_phone' => $request->merchantPhone,
            'merchant_email' => $request->merchantEmail,
            'merchant_secret' => $merchantSecret,
            'merchant_uid' => $merchantUid,
            'merchant_status' => 'DEVELOPMENT',
            'merchant_country' => $request->merchantCountry,
            'merchant_city' => $request->merchantCity,
            'merchant_website' => $request->merchantWebsite,
            'merchant_description' => $request->merchantDescription,
            'return_url' => $request->returnUrl,
            'web_service_url' => $request->webServiceUrl,
            'user_id' => $merchantUser->id,
        ]);

        // Send an email with the merchant's secret
        Mail::send('emails.merchant-secret', ['merchantEmail' => $merchantEmail,'merchantSecret' => $merchantSecret, 'adminUser' => $adminUser], function ($message) use ($adminUser) {
            $message->to($adminUser->email)
                ->subject('Merchant Account Created')
                ->attach(public_path('assets/logo.png'), [
                    'as' => 'logo.png', // The name of the file as it will appear in the email
                    'mime' => 'image/png', // MIME type of the file
                ]);
        });

        return response()->json([
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'statusCode' => 201,
            'status' => 'CREATED',
            'message' => 'Merchant account created successfully.',
            'data' => [
                'merchantId' => $merchant->id,
                'merchantName' => $merchant->merchant_name,
                'merchantEmail' => $merchant->merchant_email,
                'merchantUid' => $merchant->merchant_uid,
                // 'merchantSecret' => $merchant->merchant_secret,
            ],
        ], 201);
    }

    public function createSuperUser(Request $request)
    {
        // Validate the request
        $request->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        // Ensure only an existing SUPER user can create another SUPER user
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'SUPER') {
            return response()->json(['message' => 'Unauthorized. Only SUPER users can create other SUPER users.'], 403);
        }

        // Create the SUPER user
        $superUser = User::create([
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'SUPER',
            'is_activated' => false, // SUPER users require activation
            'company_name' => 'Norah Payment Gateway',
        ]);

        // Generate a confirmation token
        $confirmation = Confirmation::create([
            'user_id' => $superUser->id,
            'token' => bin2hex(random_bytes(16)), // Generate a random token
        ]);

        // Send confirmation email
        $confirmationUrl = url("/confirm-account?token={$confirmation->token}");
        Mail::send('emails.confirm-account', ['confirmationUrl' => $confirmationUrl, 'user' => $superUser], function ($message) use ($superUser) {
            $message->to($superUser->email)
                ->subject('Confirm Your Account')
                ->attach(public_path('assets/logo.png'), [
                    'as' => 'logo.png', // The name of the file as it will appear in the email
                    'mime' => 'image/png', // MIME type of the file
                ]);
        });

        // Return the response
        return response()->json([
            'id' => $superUser->id,
            'firstName' => $superUser->first_name,
            'lastName' => $superUser->last_name,
            'email' => $superUser->email,
            'role' => $superUser->role,
            'companyName' => $superUser->company_name,
            'enabled' => $superUser->is_activated,
        ], 201);
    }

    public function getAllMerchants()
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can view merchants.'], 403);
        }

        // Retrieve merchants belonging to the authenticated user's company
        $merchants = Merchant::whereHas('user', function ($query) use ($authenticatedUser) {
            $query->where('company_name', $authenticatedUser->company_name);
        })->get();

        if ($merchants->isEmpty()) {
            return response()->json(['message' => 'No merchants found for your company.'], 404);
        }

        // Exclude the merchant_secret field from the response
        $merchants->makeHidden(['merchant_secret']);

        return response()->json($merchants, 200);
    }

    public function inactivateMerchant($merchantId)
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can inactivate merchants.'], 403);
        }

        // Find the merchant using merchant_id
        $merchant = Merchant::where('merchant_id', $merchantId)->first();
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found.'], 404);
        }

        // Ensure the merchant belongs to the same company as the authenticated ADMIN user
        if (!$merchant->user || $merchant->user->company_name !== $authenticatedUser->company_name) {
            return response()->json(['message' => 'Unauthorized. The specified merchant does not belong to your company.'], 403);
        }

        // Inactivate the merchant
        $merchant->update(['merchant_status' => 'INACTIVE']);

            // Find the associated user and deactivate them
        $user = User::find($merchant->user_id);
        if ($user) {
            $user->update(['is_activated' => false]);
        }

        return response()->json(['message' => 'Merchant inactivated successfully.'], 200);
    }

    public function activateMerchant($merchantId)
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can activate merchants.'], 403);
        }

        // Find the merchant using merchant_id
        $merchant = Merchant::where('merchant_id', $merchantId)->first();
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found.'], 404);
        }

        // Ensure the merchant belongs to the same company as the authenticated ADMIN user
        if (!$merchant->user || $merchant->user->company_name !== $authenticatedUser->company_name) {
            return response()->json(['message' => 'Unauthorized. The specified merchant does not belong to your company.'], 403);
        }

        // Activate the merchant
        $merchant->update(['merchant_status' => 'ACTIVE']);

        // Find the associated user and activate them
        $user = User::find($merchant->user_id);
        if ($user) {
            $user->update(['is_activated' => true]);
        }

        return response()->json(['message' => 'Merchant activated successfully.'], 200);
    }


    public function getMerchantSecret($merchantId)
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can view merchant secrets.'], 403);
        }

        // Find the merchant using merchant_id
        $merchant = Merchant::where('merchant_id', $merchantId)->first();
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found.'], 404);
        }

        // Ensure the merchant belongs to the same company as the authenticated ADMIN user
        if (!$merchant->user || $merchant->user->company_name !== $authenticatedUser->company_name) {
            return response()->json(['message' => 'Unauthorized. The specified merchant does not belong to your company.'], 403);
        }

        // Return the merchant secret
        return response()->json([
            'message' => 'Please note that this is your merchant user transaction signing secret',
            'secret' => $merchant->merchant_secret,
        ], 200);
    }

    public function getUserSecret($userId)
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can view user secrets.'], 403);
        }

        // Find the user using user_id
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Ensure the user belongs to the same company as the authenticated ADMIN user
        if ($user->company_name !== $authenticatedUser->company_name) {
            return response()->json(['message' => 'Unauthorized. The specified user does not belong to your company.'], 403);
        }

        // Return the user secret
        return response()->json([
            'message' => 'Please note that this is your user transaction signing secret',
            'secret' => $user->user_secret, // Ensure this field exists in the users table
        ], 200);
    }

    public function getNonMerchantUsers()
{
    // Ensure only ADMIN users can access this endpoint
    $authenticatedUser = JWTAuth::user();
    if ($authenticatedUser->role !== 'ADMIN') {
        return response()->json(['message' => 'Unauthorized. Only ADMIN users can view non-merchant users.'], 403);
    }

    // Retrieve all non-MERCHANT users in the authenticated user's company
    $users = User::where('company_name', $authenticatedUser->company_name)
        ->where('role', '!=', 'MERCHANT')
        ->get();

    if ($users->isEmpty()) {
        return response()->json(['message' => 'No non-MERCHANT users found for your company.'], 404);
    }

    return response()->json($users, 200);
}

}
