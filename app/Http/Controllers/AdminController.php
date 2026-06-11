<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Confirmation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;


class AdminController extends Controller
{
    public function createMerchantAccount(Request $request)
    {
        // Accept the snake_case shape the form actually sends. The merchant's
        // company_name is no longer in the form - it is always inherited from
        // the parent admin so the ownership label never drifts.
        $validator = Validator::make($request->all(), [
            'merchant_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:merchants,merchant_email',
            'return_url' => 'required|string|max:255',
            'web_service_url' => 'nullable|string|max:255',
            'merchant_description' => 'nullable|string',
            'merchant_address' => 'nullable|string|max:255',
            'merchant_phone' => 'nullable|string|max:255',
            'merchant_country' => 'nullable|string|max:255',
            'merchant_city' => 'nullable|string|max:255',
            'merchant_website' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

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
            'first_name' => $request->merchant_name,
            'last_name' => 'Merchant Transaction API',
            'email' => $merchantEmail, // Generate a unique internal email
            'password' => Hash::make($merchantSecret),
            'role' => 'MERCHANT',
            'is_activated' => true,
            // Always inherit the company label from the parent admin.
            'company_name' => $adminUser->company_name,
            'primary_user' => $adminUser->id,
            'return_url' => $request->return_url,
            'web_service_url' => $request->web_service_url,
        ]);

        // Create the merchant account
        $merchant = Merchant::create([
            'merchant_name' => $request->merchant_name,
            'merchant_address' => $request->merchant_address,
            'merchant_phone' => $request->merchant_phone,
            'merchant_email' => $request->email,
            'merchant_secret' => $merchantSecret,
            'merchant_uid' => $merchantUid,
            'merchant_status' => 'DEVELOPMENT',
            'merchant_country' => $request->merchant_country,
            'merchant_city' => $request->merchant_city,
            'merchant_website' => $request->merchant_website,
            'merchant_description' => $request->merchant_description,
            'return_url' => $request->return_url,
            'web_service_url' => $request->web_service_url,
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

        // Retrieve merchants belonging to this admin. We match on the
        // primary_user ownership link rather than company_name - the latter
        // is just a label the admin can change on the merchant form, so a
        // mismatch caused new merchants to silently drop out of the list.
        // Eager-load user so company_name shows in the table.
        $merchants = Merchant::with('user:id,company_name,email,role')
            ->whereHas('user', function ($query) use ($authenticatedUser) {
                $query->where('primary_user', $authenticatedUser->id);
            })
            ->get();

        // Exclude the merchant_secret field from the response
        $merchants->makeHidden(['merchant_secret']);

        // Return the shape the front-end expects ({ success, data }).
        // An empty collection is a valid "you have no merchants yet" state,
        // not a 404 - the UI should show an empty table, not throw an error.
        return response()->json([
            'success' => true,
            'data' => $merchants,
        ], 200);
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
        if (!$merchant->user || (int) $merchant->user->primary_user !== (int) $authenticatedUser->id) {
            return response()->json(['message' => 'Unauthorized. The specified merchant does not belong to your company.'], 403);
        }

        // Inactivate the merchant
        $merchant->update(['merchant_status' => 'INACTIVE']);

            // Find the associated user and deactivate them
        $user = User::find($merchant->user_id);
        if ($user) {
            $user->update(['is_activated' => false]);
        }

        return response()->json(['success' => true, 'message' => 'Merchant inactivated successfully.'], 200);
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
        if (!$merchant->user || (int) $merchant->user->primary_user !== (int) $authenticatedUser->id) {
            return response()->json(['message' => 'Unauthorized. The specified merchant does not belong to your company.'], 403);
        }

        // Activate the merchant
        $merchant->update(['merchant_status' => 'ACTIVE']);

        // Find the associated user and activate them
        $user = User::find($merchant->user_id);
        if ($user) {
            $user->update(['is_activated' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Merchant activated successfully.'], 200);
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
        if (!$merchant->user || (int) $merchant->user->primary_user !== (int) $authenticatedUser->id) {
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

        // Admin can act on themselves or on users whose primary_user is the admin.
        if ((int) $user->id !== (int) $authenticatedUser->id
            && (int) $user->primary_user !== (int) $authenticatedUser->id) {
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

        // The admin sees themselves + any non-MERCHANT users whose primary_user
        // points at them. Company_name is just a label and would silently drop
        // self-created secondary admins if it ever drifted - use the FK link.
        $users = User::where('role', '!=', 'MERCHANT')
            ->where(function ($q) use ($authenticatedUser) {
                $q->where('id', $authenticatedUser->id)
                  ->orWhere('primary_user', $authenticatedUser->id);
            })
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'is_activated', 'primary_user', 'description']);

        return response()->json([
            'success' => true,
            'data' => $users,
        ], 200);
    }

    public function updateUser(Request $request, $userId)
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can update users.'], 403);
        }

        // Find the user
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Admin can act on themselves or on users whose primary_user is the admin.
        if ((int) $user->id !== (int) $authenticatedUser->id
            && (int) $user->primary_user !== (int) $authenticatedUser->id) {
            return response()->json(['message' => 'Unauthorized. The specified user does not belong to your company.'], 403);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|in:ADMIN,USER',
            'status' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update the user
        $nameParts = explode(' ', $request->fullName, 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

        $user->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->email,
            'role' => $request->role,
            'is_activated' => $request->status,
            'description' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ], 200);
    }

    public function deleteUser($userId)
    {
        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can delete users.'], 403);
        }

        // Find the user
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Admin can act on themselves or on users whose primary_user is the admin.
        if ((int) $user->id !== (int) $authenticatedUser->id
            && (int) $user->primary_user !== (int) $authenticatedUser->id) {
            return response()->json(['message' => 'Unauthorized. The specified user does not belong to your company.'], 403);
        }

        // Prevent deleting yourself
        if ($user->id === $authenticatedUser->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 400);
        }

        // Delete the user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ], 200);
    }

}
