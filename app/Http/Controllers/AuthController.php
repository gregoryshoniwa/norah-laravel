<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Confirmation;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }


    public function adminSignUp(Request $request)
    {
        $request->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'companyName' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'publicEmail' => 'nullable|string|email|max:255',
            'returnUrl' => 'nullable|string|max:255',
            'webServiceUrl' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
        ]);

         // Generate merchant secret and UID
        $userSecret = Str::uuid()->toString();

        $user = User::create([
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'ADMIN',
            'primary_user' => null, // No primary user for the first admin
            'company_name' => $request->companyName,
            'user_secret' => $userSecret,
            'address' => $request->address,
            'description' => $request->description,
            'phone' => $request->phone,
            'public_email' => $request->publicEmail,
            'return_url' => $request->returnUrl,
            'web_service_url' => $request->webServiceUrl,
            'website' => $request->website,
            'is_activated' => false, // Admin accounts are disabled by default
        ]);

         // Generate a confirmation token
        $confirmation = Confirmation::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(16)), // Generate a random token
        ]);

        // Send confirmation email
        $confirmationUrl = url("/confirm-account?token={$confirmation->token}");
        Mail::send('emails.confirm-account', ['confirmationUrl' => $confirmationUrl, 'user' => $user], function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Confirm Your Account')
                ->attach(public_path('assets/logo.png'), [
                    'as' => 'logo.png', // The name of the file as it will appear in the email
                    'mime' => 'image/png', // MIME type of the file
                ]);
        });

        return response()->json([
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'statusCode' => 201,
            'status' => 'CREATED',
            'message' => 'Successfully created new user. Please check your email to confirm your account.',
            'data' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'enabled' => $user->is_activated,
                'companyName' => $user->company_name,
                'userName' => $user->email,
            ],
        ], 201);
    }

    public function confirmAccount(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);

        $confirmation = Confirmation::where('token', $request->token)->first();

        if (!$confirmation) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        $user = $confirmation->user;
        $user->update(['is_activated' => true]);

        // Delete the confirmation token
        $confirmation->delete();

         // Create a SystemCharge for USD
        \App\Models\SystemCharge::create([
            'charge_type' => 'PERCENTAGE', // Set the default charge type
            'charge_source' => 'SYSTEM', // Indicate the source of the charge
            'charge_category' => 'DEFAULT', // Categorize the charge
            'status' => 'ACTIVE', // Set the status of the charge
            'currency' => 'USD', // USD currency
            'value' => 2, // Default value for the charge
            'statement_narration' => 'System charge for account activation (USD)',
            'min_threshold' => 0, // Default minimum threshold
            'max_threshold' => 1000000000, // Default maximum threshold
            'pl_account' => null, // Default PL account
            'user_email' => $user->email, // Link the charge to the user's email
            'deleted' => false, // Ensure the charge is active
        ]);

        // Create a SystemCharge for ZWG
        \App\Models\SystemCharge::create([
            'charge_type' => 'PERCENTAGE', // Set the default charge type
            'charge_source' => 'SYSTEM', // Indicate the source of the charge
            'charge_category' => 'DEFAULT', // Categorize the charge
            'status' => 'ACTIVE', // Set the status of the charge
            'currency' => 'ZWG', // ZWG currency
            'value' => 2, // Default value for the charge
            'statement_narration' => 'System charge for account activation (ZWG)',
            'min_threshold' => 0, // Default minimum threshold
            'max_threshold' => 1000000000, // Default maximum threshold
            'pl_account' => null, // Default PL account
            'user_email' => $user->email, // Link the charge to the user's email
            'deleted' => false, // Ensure the charge is active
        ]);

        return response()->json([
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'statusCode' => 200,
            'status' => 'OK',
            'message' => 'Account confirmed successfully.',
        ]);
    }

    public function signIn(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:8',
        ]);

        $credentials = $request->only('email', 'password');

        // Check if the user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Check if the user's account is activated
        if (!$user->is_activated) {
            return response()->json(['message' => 'Please activate your account from the email confirmation link.'], 403);
        }

        // Attempt to authenticate the user
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Generate a refresh token
        $refreshToken = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'refreshToken' => $refreshToken,
            'tokenExpiryDate' => now()->addHours(1)->toRfc7231String(),
            'refreshTokenExpiryDate' => now()->addDays(7)->toRfc7231String(),
            'user_id' => $user->id,
            'role' => $user->role,
            'fullName' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'companyName' => $user->company_name,
            'logo' => $user->logo,
        ]);
    }

    public function resendConfirmationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if the user is already activated
        if ($user->is_activated) {
            return response()->json([
                'message' => 'This account is already activated.',
            ], 400);
        }

        // Generate a new confirmation token
        $confirmation = Confirmation::updateOrCreate(
            ['user_id' => $user->id],
            ['token' => bin2hex(random_bytes(16))]
        );

        // Send the confirmation email
        $confirmationUrl = url("/confirm-account?token={$confirmation->token}");
        Mail::send('emails.confirm-account', ['confirmationUrl' => $confirmationUrl, 'user' => $user], function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Confirm Your Account')
                ->attach(public_path('assets/logo.png'), [
                    'as' => 'logo.png', // The name of the file as it will appear in the email
                    'mime' => 'image/png', // MIME type of the file
                ]);
        });

        return response()->json([
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'statusCode' => 200,
            'status' => 'OK',
            'message' => 'Confirmation email resent successfully.',
            'data' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'enabled' => $user->is_activated,
                'companyName' => $user->company_name,
                'userName' => $user->email,
            ],
        ]);
    }

    public function refreshToken(Request $request)
    {
        try {
            // Refresh the token
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'token' => $newToken,
                'tokenExpiryDate' => now()->addHours(1)->toRfc7231String(),
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate a unique token for the password reset
        $token = Str::random(60);

        // Store the token in the password_resets table
        DB::table('password_resets')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => $token,
                'created_at' => now(),
            ]
        );

        // Send the reset email
        $resetUrl = url("/reset-password?token=$token&email={$user->email}");
        Mail::send('emails.forgot-password', ['resetUrl' => $resetUrl, 'user' => $user], function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Reset Your Password')
                ->attach(public_path('assets/logo.png'), [
                    'as' => 'logo.png', // The name of the file as it will appear in the email
                    'mime' => 'image/png', // MIME type of the file
                ]);
        });

        return response()->json([
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'statusCode' => 200,
            'status' => 'OK',
            'message' => 'Successfully sent forget password confirmation',
            'path' => '/api/v1/auth/forgot-password',
            'requestMethod' => 'POST',
            'data' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'enabled' => $user->is_activated,
                'companyName' => $user->company_name,
                'userName' => $user->email,
            ],
        ]);
    }

    public function showResetPasswordForm(Request $request)
{
    return view('auth.reset-password', [
        'token' => $request->query('token'),
        'email' => $request->query('email'),
    ]);
}

public function resetPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
        'password' => 'required|string|min:8|confirmed',
        'token' => 'required',
    ]);

    // Check if the token exists in the password_resets table
    $reset = DB::table('password_resets')->where([
        'email' => $request->email,
        'token' => $request->token,
    ])->first();

    if (!$reset) {
        return back()->withErrors(['email' => 'Invalid token or email.']);
    }

    // Update the user's password
    $user = User::where('email', $request->email)->first();
    $user->update(['password' => Hash::make($request->password)]);

    // Delete the reset token
    DB::table('password_resets')->where(['email' => $request->email])->delete();

    return response()->json([
        'timestamp' => now()->format('Y-m-d H:i:s'),
        'statusCode' => 200,
        'status' => 'OK',
        'message' => 'Password reset successfully.',
        'path' => '/api/reset-password',
        'requestMethod' => 'POST',
    ]);
}


public function merchantSignIn(Request $request)
{
    // Validate the request
    $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string|min:8',
    ]);

    // Attempt to authenticate the user
    $credentials = $request->only('email', 'password');
    if (!$token = JWTAuth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // Get the authenticated user
    $user = JWTAuth::user();

    // Check if the user has the "MERCHANT" role
    if ($user->role !== 'MERCHANT') {
        return response()->json(['message' => 'Unauthorized. Only merchants can sign in here.'], 403);
    }

    // Return the token and user details
    return response()->json([
        'token' => $token,
        'token_type' => 'bearer',
        'expires_in' => now()->addHours(1)->toRfc7231String(),
        'user' => [
            'id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
        ],
    ]);
}

public function secondaryAdminSignUp(Request $request)
{
    // Validate the request
    $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8',
    ]);

    // Get the authenticated admin user
    $primaryUser = JWTAuth::user();

    // Ensure the authenticated user has the "ADMIN" role
    if ($primaryUser->role !== 'ADMIN') {
        return response()->json(['message' => 'Unauthorized. Only admins can create secondary admin accounts.'], 403);
    }

    // Generate merchant secret and UID
    $userSecret = Str::uuid()->toString();

    // Create the secondary admin user
    $secondaryAdmin = User::create([
        'first_name' => $request->firstName,
        'last_name' => $request->lastName,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'ADMIN',
        'is_activated' => false, // Secondary admin accounts are inactive by default
        'primary_user' => $primaryUser->id,
        'company_name' => $primaryUser->company_name,
        'user_secret' => $userSecret,
        'description' => $primaryUser->description,
        'phone' => $request->phone ?? $primaryUser->phone,
        'website' => $primaryUser->website,
        'address' => $primaryUser->address,
        'public_email' => $primaryUser->public_email,
        'return_url' => $primaryUser->return_url,
        'web_service_url' => $primaryUser->web_service_url,
    ]);

    // Generate a confirmation token
    $confirmation = Confirmation::create([
        'user_id' => $secondaryAdmin->id,
        'token' => bin2hex(random_bytes(16)), // Generate a random token
    ]);

    // Send confirmation email
    $confirmationUrl = url("/confirm-account?token={$confirmation->token}");
    Mail::send('emails.confirm-account', ['confirmationUrl' => $confirmationUrl, 'user' => $secondaryAdmin], function ($message) use ($secondaryAdmin) {
        $message->to($secondaryAdmin->email)
            ->subject('Confirm Your Account')
            ->attach(public_path('assets/logo.png'), [
                'as' => 'logo.png', // The name of the file as it will appear in the email
                'mime' => 'image/png', // MIME type of the file
            ]);
    });

    // Return the response
    return response()->json([
        'id' => $secondaryAdmin->id,
        'firstName' => $secondaryAdmin->first_name,
        'lastName' => $secondaryAdmin->last_name,
        'email' => $secondaryAdmin->email,
        // 'password' => $secondaryAdmin->password, // Hashed password
        'role' => $secondaryAdmin->role,
        'primaryUser' => $primaryUser->id,
        'companyName' => $secondaryAdmin->company_name,
        'enabled' => $secondaryAdmin->is_activated,
        'authorities' => [
            ['authority' => $secondaryAdmin->role],
        ],
        'username' => $secondaryAdmin->email,
        'accountNonExpired' => true,
        'accountNonLocked' => true,
        'credentialsNonExpired' => true,
    ], 201);
}
}
