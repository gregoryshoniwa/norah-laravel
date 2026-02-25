<?php

namespace App\Http\Controllers;

use App\Models\TransactionAudit;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class TransactionAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = JWTAuth::user();

        if (!$user || !in_array($user->role, ['ADMIN', 'SUPER'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only ADMIN or SUPER users can view transaction audits.',
            ], 403);
        }

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $query = TransactionAudit::query()
            ->with(['transaction:id,trace,reference,status,payment_method,amount,currency', 'user:id,email,role'])
            ->orderByDesc('id');

        if ($request->filled('trace')) {
            $query->where('trace', $request->input('trace'));
        }

        if ($request->filled('transaction_id')) {
            $query->where('transaction_id', (int) $request->input('transaction_id'));
        }

        if ($request->filled('reference')) {
            $query->where('reference', $request->input('reference'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', strtoupper($request->input('payment_method')));
        }

        if ($request->filled('level')) {
            $query->where('level', strtoupper($request->input('level')));
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->input('stage'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('provider')) {
            $query->where('provider', strtoupper($request->input('provider')));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->whereRaw('LOWER(event) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(stage) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(payment_method) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(provider) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(trace) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(reference) LIKE ?', ["%{$search}%"]);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }
}

