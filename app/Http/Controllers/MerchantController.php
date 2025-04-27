<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Merchant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MerchantController extends Controller
{
    public function index(Request $request)
    {
        $query = Merchant::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('merchant_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('company_name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->per_page ?? 10)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'merchant_name' => 'required|string|max:255',
            'email' => 'required|email|unique:merchants',
            'company_name' => 'required|string|max:255',
            'merchant_description' => 'nullable|string',
            'return_url' => 'required|url',
            'password' => 'required|string|min:8'
        ]);

        $merchant = new Merchant($request->all());
        $merchant->password = Hash::make($request->password);
        $merchant->merchant_key = Str::random(32);
        $merchant->merchant_secret = Str::random(64);
        $merchant->status = true;
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => 'Merchant created successfully',
            'data' => $merchant
        ]);
    }

    public function show($id)
    {
        $merchant = Merchant::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $merchant
        ]);
    }

    public function update(Request $request, $id)
    {
        $merchant = Merchant::findOrFail($id);

        $request->validate([
            'merchant_name' => 'required|string|max:255',
            'email' => 'required|email|unique:merchants,email,' . $id,
            'company_name' => 'required|string|max:255',
            'merchant_description' => 'nullable|string',
            'return_url' => 'required|url'
        ]);

        $merchant->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Merchant updated successfully',
            'data' => $merchant
        ]);
    }

    public function destroy($id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Merchant deleted successfully'
        ]);
    }

    public function activate($id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->status = true;
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => 'Merchant activated successfully',
            'data' => $merchant
        ]);
    }

    public function deactivate($id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->status = false;
        $merchant->save();

        return response()->json([
            'success' => true,
            'message' => 'Merchant deactivated successfully',
            'data' => $merchant
        ]);
    }

    public function getSecret($id)
    {
        $merchant = Merchant::findOrFail($id);
        return response()->json([
            'success' => true,
            'secret' => $merchant->merchant_secret
        ]);
    }
}
