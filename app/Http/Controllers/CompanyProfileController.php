<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Self-service profile for ADMIN, MERCHANT (and SUPER acting on others).
 *
 *  GET  /api/v1/profile           - own profile (URLs + bank info)
 *  PUT  /api/v1/profile           - update own profile
 *  PUT  /api/v1/profile/bank      - update own bank only
 *  GET  /api/v1/super/profile/{userId} - SUPER: read any recipient
 *  PUT  /api/v1/super/profile/{userId} - SUPER: edit any recipient
 *
 * Bank fields are exposed because payouts need them. They are kept on the
 * users table so every payable role (ADMIN / MERCHANT) has its own.
 */
class CompanyProfileController extends Controller
{
    public function show()
    {
        $user = JWTAuth::user();
        return response()->json(['success' => true, 'data' => $this->profileShape($user->fresh())]);
    }

    public function update(Request $request)
    {
        $user = JWTAuth::user();
        return $this->applyUpdate($user, $request);
    }

    public function updateBank(Request $request)
    {
        $user = JWTAuth::user();

        $validator = Validator::make($request->all(), $this->bankRules());
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $user->update($request->only(array_keys($this->bankRules())));
        return response()->json(['success' => true, 'data' => $this->profileShape($user->fresh())]);
    }

    /** Super-admin: read any user's profile (for the payouts page). */
    public function superShow($userId)
    {
        $caller = JWTAuth::user();
        if (!$caller || $caller->role !== 'SUPER') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $user = User::find($userId);
        if (!$user) return response()->json(['message' => 'User not found.'], 404);
        return response()->json(['success' => true, 'data' => $this->profileShape($user)]);
    }

    /** Super-admin: edit any user's profile. */
    public function superUpdate(Request $request, $userId)
    {
        $caller = JWTAuth::user();
        if (!$caller || $caller->role !== 'SUPER') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $user = User::find($userId);
        if (!$user) return response()->json(['message' => 'User not found.'], 404);
        return $this->applyUpdate($user, $request);
    }

    private function applyUpdate(User $user, Request $request)
    {
        $rules = array_merge([
            'company_name' => 'sometimes|nullable|string|max:255',
            'return_url' => 'sometimes|nullable|url|max:500',
            'web_service_url' => 'sometimes|nullable|url|max:500',
        ], $this->bankRules());

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $user->update($request->only(array_keys($rules)));
        return response()->json(['success' => true, 'data' => $this->profileShape($user->fresh())]);
    }

    private function bankRules(): array
    {
        return [
            'bank_name' => 'sometimes|nullable|string|max:120',
            'bank_branch' => 'sometimes|nullable|string|max:120',
            'bank_account_name' => 'sometimes|nullable|string|max:120',
            'bank_account_number' => 'sometimes|nullable|string|max:50',
            'bank_swift_code' => 'sometimes|nullable|string|max:20',
        ];
    }

    private function profileShape(User $u): array
    {
        return [
            'id' => $u->id,
            'email' => $u->email,
            'role' => $u->role,
            'company_name' => $u->company_name,
            'return_url' => $u->return_url,
            'web_service_url' => $u->web_service_url,
            'bank_name' => $u->bank_name,
            'bank_branch' => $u->bank_branch,
            'bank_account_name' => $u->bank_account_name,
            'bank_account_number' => $u->bank_account_number,
            'bank_swift_code' => $u->bank_swift_code,
            'bank_complete' => self::hasBankInfo($u),
        ];
    }

    public static function hasBankInfo(User $u): bool
    {
        return !empty($u->bank_name) && !empty($u->bank_account_number) && !empty($u->bank_account_name);
    }
}
