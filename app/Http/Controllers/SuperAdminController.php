<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\SystemCharge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Confirmation;
use Tymon\JWTAuth\Facades\JWTAuth;

class SuperAdminController extends Controller
{
    private function requireSuper()
    {
        $user = JWTAuth::user();
        if (!$user || $user->role !== 'SUPER') {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only SUPER users can access this resource.',
            ], 403));
        }
        return $user;
    }

    // ─── Dashboard Stats ───

    public function getDashboardStats(Request $request)
    {
        $this->requireSuper();
        $currency = $request->input('currency', 'USD');

        $companies = User::where('role', 'ADMIN')
            ->distinct('company_name')
            ->count('company_name');

        $totalMerchants = Merchant::count();
        $activeMerchants = Merchant::where('merchant_status', 'ACTIVE')->count();

        $totalTransactions = Transaction::whereIn('type', ['PAYMENT'])
            ->where('currency', $currency)
            ->count();
        $totalVolume = Transaction::whereIn('type', ['PAYMENT'])
            ->where('status', 'COMPLETED')
            ->where('currency', $currency)
            ->sum('numeric_amount');

        $completedCount = Transaction::where('type', 'PAYMENT')->where('status', 'COMPLETED')->where('currency', $currency)->count();
        $pendingCount = Transaction::where('type', 'PAYMENT')->where('status', 'PENDING')->where('currency', $currency)->count();
        $failedCount = Transaction::where('type', 'PAYMENT')->where('status', 'FAILED')->where('currency', $currency)->count();

        $superUsers = User::where('role', 'SUPER')->count();

        // Total profit = system charges + merchant charges (our revenue from charges)
        $systemCharges = Transaction::where('type', 'SYSTEM_CHARGE')->where('status', 'COMPLETED')->where('currency', $currency)->sum('amount');
        $merchantCharges = Transaction::where('type', 'MERCHANT_CHARGE')->where('status', 'COMPLETED')->where('currency', $currency)->sum('amount');
        $totalProfit = $systemCharges + $merchantCharges;

        $recentTransactions = Transaction::whereIn('type', ['PAYMENT'])
            ->where('currency', $currency)
            ->with('user:id,email,company_name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $volumeByMethod = Transaction::where('type', 'PAYMENT')
            ->where('status', 'COMPLETED')
            ->where('currency', $currency)
            ->select('payment_method', DB::raw('SUM(numeric_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'companies' => $companies,
                'totalMerchants' => $totalMerchants,
                'activeMerchants' => $activeMerchants,
                'totalTransactions' => $totalTransactions,
                'totalVolume' => $totalVolume,
                'totalProfit' => $totalProfit,
                'completed' => $completedCount,
                'pending' => $pendingCount,
                'failed' => $failedCount,
                'superUsers' => $superUsers,
                'currency' => $currency,
                'recentTransactions' => $recentTransactions,
                'volumeByMethod' => $volumeByMethod,
            ],
        ]);
    }

    // ─── All Merchants (system-wide) ───

    public function getAllMerchants(Request $request)
    {
        $this->requireSuper();

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $query = Merchant::with('user:id,email,company_name,role,is_activated');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('merchant_name', 'LIKE', "%{$search}%")
                  ->orWhere('merchant_email', 'LIKE', "%{$search}%")
                  ->orWhere('merchant_uid', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('company_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('merchant_status', $request->input('status'));
        }

        $merchants = $query->orderByDesc('created_at')->paginate($perPage);
        $merchants->makeHidden(['merchant_secret']);

        return response()->json([
            'success' => true,
            'data' => $merchants,
        ]);
    }

    // ─── All Transactions (system-wide) ───

    public function getAllTransactions(Request $request)
    {
        $this->requireSuper();

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $query = Transaction::whereIn('type', ['PAYMENT'])
            ->with('user:id,email,company_name,role');

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhere('reference', 'LIKE', "%{$search}%")
                  ->orWhere('trace', 'LIKE', "%{$search}%")
                  ->orWhere('user_name', 'LIKE', "%{$search}%")
                  ->orWhere('payment_method', 'LIKE', "%{$search}%")
                  ->orWhere('merchant_uid', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        if ($request->filled('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        $transactions = $query->orderByDesc('created_at')->paginate($perPage);

        // Attach merchant/company info
        $transactions->getCollection()->transform(function ($txn) {
            $merchant = null;
            if ($txn->merchant_uid) {
                $merchant = Merchant::where('merchant_uid', $txn->merchant_uid)->first(['merchant_name', 'merchant_email', 'merchant_uid']);
            }
            $txn->merchant = $merchant;
            $txn->company_name = $txn->user->company_name ?? null;
            return $txn;
        });

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    // ─── Super Users Management ───

    public function getSuperUsers(Request $request)
    {
        $this->requireSuper();

        $users = User::where('role', 'SUPER')->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $users->makeHidden(['password', 'remember_token', 'user_secret']),
        ]);
    }

    public function createSuperUser(Request $request)
    {
        $this->requireSuper();

        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $superUser = User::create([
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'SUPER',
            'is_activated' => false,
            'company_name' => 'Norah Payment Gateway',
        ]);

        $confirmation = Confirmation::create([
            'user_id' => $superUser->id,
            'token' => bin2hex(random_bytes(16)),
        ]);

        try {
            $confirmationUrl = url("/confirm-account?token={$confirmation->token}");
            Mail::send('emails.confirm-account', ['confirmationUrl' => $confirmationUrl, 'user' => $superUser], function ($message) use ($superUser) {
                $message->to($superUser->email)->subject('Confirm Your Account');
            });
        } catch (\Exception $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Super user created. Confirmation email sent.',
            'data' => $superUser->makeHidden(['password', 'remember_token', 'user_secret']),
        ], 201);
    }

    public function updateSuperUser(Request $request, $userId)
    {
        $authenticatedUser = $this->requireSuper();

        $user = User::where('id', $userId)->where('role', 'SUPER')->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Super user not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'is_activated' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('firstName')) $user->first_name = $request->firstName;
        if ($request->has('lastName')) $user->last_name = $request->lastName;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('is_activated')) $user->is_activated = $request->is_activated;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Super user updated.',
            'data' => $user->makeHidden(['password', 'remember_token', 'user_secret']),
        ]);
    }

    public function deleteSuperUser($userId)
    {
        $authenticatedUser = $this->requireSuper();

        if ((int) $userId === $authenticatedUser->id) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 400);
        }

        $user = User::where('id', $userId)->where('role', 'SUPER')->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Super user not found.'], 404);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'Super user deleted.']);
    }

    // ─── Companies Overview ───

    public function getCompanies(Request $request)
    {
        $this->requireSuper();

        $companies = User::where('role', 'ADMIN')
            ->select('company_name', DB::raw('COUNT(*) as admin_count'), DB::raw('MIN(created_at) as created_at'))
            ->groupBy('company_name')
            ->get()
            ->map(function ($company) {
                $merchantCount = Merchant::whereHas('user', function ($q) use ($company) {
                    $q->where('company_name', $company->company_name);
                })->count();

                $transactionCount = Transaction::where('type', 'PAYMENT')
                    ->whereHas('user', function ($q) use ($company) {
                        $q->where('company_name', $company->company_name);
                    })->count();

                $volume = Transaction::where('type', 'PAYMENT')
                    ->where('status', 'COMPLETED')
                    ->whereHas('user', function ($q) use ($company) {
                        $q->where('company_name', $company->company_name);
                    })->sum('numeric_amount');

                return [
                    'company_name' => $company->company_name,
                    'admin_count' => $company->admin_count,
                    'merchant_count' => $merchantCount,
                    'transaction_count' => $transactionCount,
                    'volume' => $volume,
                    'created_at' => $company->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }
}
