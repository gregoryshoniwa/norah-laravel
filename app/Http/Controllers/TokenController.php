<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Merchant;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tymon\JWTAuth\Facades\JWTAuth;

class TokenController extends Controller
{
    public function validateUserToken(Request $request)
    {
        $token = $request->token;
        $authenticatedUser = JWTAuth::user();

        try {
            $decoded = JWT::decode($token, new Key($authenticatedUser->user_secret, 'HS256'));
            return response()->json(['message' => 'User token is valid.', 'data' => $decoded], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid user token.', 'error' => $e->getMessage()], 400);
        }
    }
    public function validateMerchantToken(Request $request)
    {
        $token = $request->token;
        $authenticatedUser = JWTAuth::user();

        // Find the merchant associated with the authenticated user
        $merchant = Merchant::where('user_id', $authenticatedUser->id)->first();
        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found.'], 404);
        }

        try {
            $decoded = JWT::decode($token, new Key($merchant->merchant_secret, 'HS256'));
            return response()->json(['message' => 'Merchant token is valid.', 'data' => $decoded], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid merchant token.', 'error' => $e->getMessage()], 400);
        }
    }

    public function decodeAndValidateToken(Request $request)
    {
        $token = $request->token;
        $type = $request->type;

        if (!$token || !$type) {
            return response()->json(['message' => 'Token or type is missing.'], 400);
        }

        try {
            // Decode the token to extract the payload
            $decodedToken = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256')); // Temporarily decode using a general key
            $userEmail = $decodedToken->user ?? null;

            if (!$userEmail) {
                return response()->json(['message' => 'Invalid token. User information is missing.'], 400);
            }

            if ($type === 'u') {
                // Validate user token
                $user = \App\Models\User::where('email', $userEmail)->first();

                if (!$user) {
                    return response()->json(['message' => 'User not found.'], 404);
                }



                // Decode the token using the user's secret
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                $decoded->logo = $user->logo;
                return response()->json(['message' => 'User token is valid.', 'data' => $decoded], 200);
            } elseif ($type === 'm') {
                // Validate merchant token
                $user = \App\Models\User::where('email', $userEmail)->first();

                if (!$user) {
                    return response()->json(['message' => 'User not found.'], 404);
                }

                $merchant = Merchant::where('user_id', $user->id)->first();

                if (!$merchant) {
                    return response()->json(['message' => 'Merchant not found.'], 404);
                }



                // Decode the token using the merchant's secret
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                // $decoded->user = $merchant->merchant_name;
                $decoded->logo = $merchant->merchant_logo;
                return response()->json(['message' => 'Merchant token is valid.', 'data' => $decoded], 200);
            } else {
                return response()->json(['message' => 'Invalid token type.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token.', 'message' => $e->getMessage()], 400);
        }
    }
}
