<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\SystemCharge;
use App\Models\User;

class ChargesController extends Controller
{
    public function addSystemCharge(Request $request)
    {
        // Validate the request
        $request->validate([
            'chargeType' => 'required|string|max:255',
            'chargeSource' => 'required|string|max:255',
            'chargeCategory' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'value' => 'required|numeric|min:0',
            'statementNarration' => 'required|string|max:255',
            'minThreshold' => 'required|numeric|min:0',
            'maxThreshold' => 'required|numeric|min:0',
            'plAccount' => 'required|string|max:255',
        ]);

        // Ensure only SUPER users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'SUPER') {
            return response()->json(['message' => 'Unauthorized. Only SUPER users can add system charges.'], 403);
        }

        // Create the system charge
        $systemCharge = SystemCharge::create([
            'charge_type' => $request->chargeType,
            'charge_source' => $request->chargeSource,
            'charge_category' => $request->chargeCategory,
            'status' => $request->status,
            'currency' => $request->currency,
            'value' => $request->value,
            'statement_narration' => $request->statementNarration,
            'min_threshold' => $request->minThreshold,
            'max_threshold' => $request->maxThreshold,
            'pl_account' => $request->plAccount,
            'deleted' => false,
        ]);

        // Return the response
        return response()->json([
            'id' => $systemCharge->id,
            'createdBy' => $authenticatedUser->email,
            'created' => $systemCharge->created_at->toRfc3339String(),
            'modifiedBy' => null,
            'modified' => null,
            'modificationType' => 'CREATE',
            'deleted' => $systemCharge->deleted,
            'uid' => $systemCharge->uid,
            'chargeType' => $systemCharge->charge_type,
            'chargeSource' => $systemCharge->charge_source,
            'chargeCategory' => $systemCharge->charge_category,
            'status' => $systemCharge->status,
            'currency' => $systemCharge->currency,
            'value' => $systemCharge->value,
            'statementNarration' => $systemCharge->statement_narration,
            'minThreshold' => $systemCharge->min_threshold,
            'maxThreshold' => $systemCharge->max_threshold,
            'plAccount' => $systemCharge->pl_account,
        ], 201);
    }

    public function getAllSystemCharges()
    {
        // Ensure only SUPER users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'SUPER') {
            return response()->json(['message' => 'Unauthorized. Only SUPER users can view system charges.'], 403);
        }

        // Retrieve all system charges
        $systemCharges = SystemCharge::all();

        return response()->json($systemCharges, 200);
    }

    public function updateSystemCharge(Request $request, $id)
    {
        // Validate the request
        $request->validate([
            'chargeType' => 'sometimes|string|max:255',
            'chargeSource' => 'sometimes|string|max:255',
            'chargeCategory' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:255',
            'currency' => 'sometimes|string|max:3',
            'value' => 'sometimes|numeric|min:0',
            'statementNarration' => 'sometimes|string|max:255',
            'minThreshold' => 'sometimes|numeric|min:0',
            'maxThreshold' => 'sometimes|numeric|min:0',
            'plAccount' => 'sometimes|string|max:255',
        ]);

        // Ensure only SUPER users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'SUPER') {
            return response()->json(['message' => 'Unauthorized. Only SUPER users can update system charges.'], 403);
        }

        // Find the system charge
        $systemCharge = SystemCharge::find($id);
        if (!$systemCharge) {
            return response()->json(['message' => 'System charge not found.'], 404);
        }

        // Update the system charge
        $systemCharge->update($request->all());
        $systemCharge->modified_by = $authenticatedUser->email;
        $systemCharge->save();

        return response()->json([
            'message' => 'System charge updated successfully.',
            'data' => $systemCharge,
        ], 200);
    }

    public function deleteSystemCharge($id)
    {
        // Ensure only SUPER users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'SUPER') {
            return response()->json(['message' => 'Unauthorized. Only SUPER users can delete system charges.'], 403);
        }

        // Find the system charge
        $systemCharge = SystemCharge::find($id);
        if (!$systemCharge) {
            return response()->json(['message' => 'System charge not found.'], 404);
        }

        // Soft delete the system charge
        $systemCharge->delete();

        return response()->json(['message' => 'System charge deleted successfully.'], 200);
    }



    public function addMerchantCharge(Request $request)
    {
        // Validate the request
        $request->validate([
            'merchantUserName' => 'required|string|exists:users,email', // Ensure the merchant exists
            'chargeType' => 'required|string|max:255',
            'chargeSource' => 'required|string|max:255',
            'chargeCategory' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'value' => 'required|numeric|min:0',
            'statementNarration' => 'required|string|max:255',
            'minThreshold' => 'required|numeric|min:0',
            'maxThreshold' => 'required|numeric|min:0',
            'plAccount' => 'required|string|max:255|unique:charges,pl_account',
        ]);

        // Ensure only ADMIN users can access this endpoint
        $authenticatedUser = JWTAuth::user();
        if ($authenticatedUser->role !== 'ADMIN') {
            return response()->json(['message' => 'Unauthorized. Only ADMIN users can add merchant charges.'], 403);
        }

        // Check if the merchant belongs to the same company as the authenticated ADMIN user
        $merchant = User::where('email', $request->merchantUserName)->first();
        if (!$merchant || $merchant->company_name !== $authenticatedUser->company_name) {
            return response()->json(['message' => 'Unauthorized. The specified merchant does not belong to the same company as this ADMIN user.'], 403);
        }

        // Check if the merchant exists in the merchants table
        $merchantRecord = Merchant::where('user_id', $merchant->id)->first();
        if (!$merchantRecord) {
            return response()->json(['message' => 'Unauthorized. The specified merchant is not registered.'], 403);
        }

        // Create the charge for the merchant
        $charge = Charge::create([
            'charge_type' => $request->chargeType,
            'charge_source' => $request->chargeSource,
            'charge_category' => $request->chargeCategory,
            'status' => $request->status,
            'currency' => $request->currency,
            'value' => $request->value,
            'statement_narration' => $request->statementNarration,
            'min_threshold' => $request->minThreshold,
            'max_threshold' => $request->maxThreshold,
            'pl_account' => $request->plAccount,
            'merchant_user_name' => $request->merchantUserName, // Associate the charge with the merchant
            'deleted' => false,
        ]);

        // Return the response
        return response()->json([
            'message' => 'Charge added successfully for the merchant.',
            'data' => $charge,
        ], 201);
    }

    public function getAllCharges()
{
    // Ensure only ADMIN users can access this endpoint
    $authenticatedUser = JWTAuth::user();
    if ($authenticatedUser->role !== 'ADMIN') {
        return response()->json(['message' => 'Unauthorized. Only ADMIN users can view charges.'], 403);
    }

    // Retrieve charges only for the authenticated user's company
    $charges = Charge::whereHas('merchant', function ($query) use ($authenticatedUser) {
        $query->where('company_name', $authenticatedUser->company_name);
    })->get();

    if ($charges->isEmpty()) {
        return response()->json(['message' => 'No charges found for your company.'], 404);
    }

    return response()->json($charges, 200);
}

public function getChargesByMerchant($merchantUserName)
{
    // Ensure only ADMIN users can access this endpoint
    $authenticatedUser = JWTAuth::user();
    if ($authenticatedUser->role !== 'ADMIN') {
        return response()->json(['message' => 'Unauthorized. Only ADMIN users can view charges.'], 403);
    }

    // Check if the merchant exists and belongs to the same company as the authenticated ADMIN user
    $merchant = User::where('email', $merchantUserName)->first();
    if (!$merchant || $merchant->company_name !== $authenticatedUser->company_name) {
        return response()->json(['message' => 'Unauthorized. The specified merchant does not belong to the same company as this ADMIN user.'], 403);
    }

    // Retrieve charges for the specified merchant
    $charges = Charge::where('merchant_user_name', $merchantUserName)->get();

    if ($charges->isEmpty()) {
        return response()->json(['message' => 'No charges found for the specified merchant.'], 404);
    }

    return response()->json($charges, 200);
}

public function updateCharge(Request $request, $id)
{
    // Ensure only ADMIN users can access this endpoint
    $authenticatedUser = JWTAuth::user();
    if ($authenticatedUser->role !== 'ADMIN') {
        return response()->json(['message' => 'Unauthorized. Only ADMIN users can update charges.'], 403);
    }

    // Validate the request
    $request->validate([
        'chargeType' => 'sometimes|string|max:255',
        'chargeSource' => 'sometimes|string|max:255',
        'chargeCategory' => 'sometimes|string|max:255',
        'status' => 'sometimes|string|max:255',
        'currency' => 'sometimes|string|max:3',
        'value' => 'sometimes|numeric|min:0',
        'statementNarration' => 'sometimes|string|max:255',
        'minThreshold' => 'sometimes|numeric|min:0',
        'maxThreshold' => 'sometimes|numeric|min:0',
        'plAccount' => 'sometimes|string|max:255|unique:charges,pl_account,' . $id,
    ]);

    // Find the charge
    $charge = Charge::find($id);
    if (!$charge) {
        return response()->json(['message' => 'Charge not found.'], 404);
    }

    // Ensure the charge belongs to the same company as the authenticated ADMIN user
    if (!$charge->merchant || $charge->merchant->user->company_name !== $authenticatedUser->company_name) {
        return response()->json(['message' => 'Unauthorized. The specified charge does not belong to your company.'], 403);
    }

    // Update the charge
    $charge->update($request->all());

    return response()->json([
        'message' => 'Charge updated successfully.',
        'data' => $charge,
    ], 200);
}

public function deleteCharge($id)
{
    // Ensure only ADMIN users can access this endpoint
    $authenticatedUser = JWTAuth::user();
    if ($authenticatedUser->role !== 'ADMIN') {
        return response()->json(['message' => 'Unauthorized. Only ADMIN users can delete charges.'], 403);
    }

    // Find the charge
    $charge = Charge::find($id);
    if (!$charge) {
        return response()->json(['message' => 'Charge not found.'], 404);
    }

    // Ensure the charge belongs to the same company as the authenticated ADMIN user
    if (!$charge->merchant || $charge->merchant->user->company_name !== $authenticatedUser->company_name) {
        return response()->json(['message' => 'Unauthorized. The specified charge does not belong to your company.'], 403);
    }

    // Soft delete the charge
    $charge->delete();

    return response()->json(['message' => 'Charge deleted successfully.'], 200);
}


}
