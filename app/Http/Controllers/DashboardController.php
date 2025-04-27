<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $currency = $request->currency ?? 'USD';

        $stats = [
            'totalVolume' => Transaction::where('currency', $currency)->where('type', 'PAYMENT')->sum('amount'),
            'totalTransactions' => Transaction::where('currency', $currency)->where('type', 'PAYMENT')->count(),
            'systemCharges' => Transaction::where('currency', $currency)->where('type', 'SYSTEM_CHARGE')->sum('amount'),
            'merchantCharges' => Transaction::where('currency', $currency)->where('type', 'MERCHANT_CHARGE')->sum('amount'),
            'currencies' => ['USD', 'ZWG', 'ZAR', 'BWP', 'GBP'],
            'currentCurrency' => $currency
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function getRecentTransactions(Request $request)
    {
        $query = Transaction::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhere('reference', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->per_page ?? 5)
        ]);
    }
}
