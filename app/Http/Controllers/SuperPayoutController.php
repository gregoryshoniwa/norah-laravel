<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\PayoutMessage;
use App\Models\PayoutSchedule;
use App\Models\PayoutTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PayoutBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SuperPayoutController extends Controller
{
    protected PayoutBalanceService $balances;

    public function __construct(PayoutBalanceService $balances)
    {
        $this->balances = $balances;
    }

    private function authSuper()
    {
        $user = JWTAuth::user();
        if (!$user || !in_array($user->role, ['SUPER', 'ADMIN'], true)) {
            return null;
        }
        return $user;
    }

    /**
     * GET /api/v1/super/payouts/outstanding
     * One row per recipient with their currently-owed balance.
     * Filters: currency, role (ADMIN/MERCHANT), parent_user_id, payment_method.
     */
    public function outstanding(Request $request)
    {
        if (!$this->authSuper()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $roleFilter = strtoupper($request->get('role', '')); // '', ADMIN, MERCHANT
        $recipients = User::query()
            ->whereIn('role', ['ADMIN', 'MERCHANT'])
            ->when($roleFilter, fn ($q) => $q->where('role', $roleFilter))
            ->when($request->filled('parent_user_id'), fn ($q) => $q->where('primary_user', $request->parent_user_id))
            ->get(['id', 'first_name', 'last_name', 'email', 'company_name', 'role', 'primary_user']);

        $rows = [];
        foreach ($recipients as $u) {
            $breakdown = $this->balances->expectedFor($u)['by_currency'];

            // Apply currency / payment_method filters at display time
            if ($request->filled('currency')) {
                $cur = strtoupper($request->currency);
                $breakdown = array_values(array_filter($breakdown, fn ($b) => $b['currency'] === $cur));
            }
            if ($request->filled('payment_method')) {
                $pm = strtoupper($request->payment_method);
                foreach ($breakdown as &$b) {
                    $b['by_method'] = array_values(array_filter($b['by_method'], fn ($m) => strtoupper($m['payment_method']) === $pm));
                    $b['total'] = array_reduce($b['by_method'], fn ($carry, $m) => bcadd($carry, (string) $m['total'], 2), '0.00');
                }
                unset($b);
                $breakdown = array_values(array_filter($breakdown, fn ($b) => bccomp($b['total'], '0', 2) > 0));
            }
            if (empty($breakdown)) {
                continue;
            }

            $rows[] = [
                'user_id' => $u->id,
                'name' => trim($u->company_name ?: ($u->first_name . ' ' . $u->last_name)) ?: $u->email,
                'email' => $u->email,
                'role' => $u->role,
                'parent_user_id' => $u->primary_user,
                'breakdown' => $breakdown,
                'bank_complete' => \App\Http\Controllers\CompanyProfileController::hasBankInfo($u),
                'bank_name' => $u->bank_name,
                'bank_account_name' => $u->bank_account_name,
                'bank_account_number' => $u->bank_account_number,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * POST /api/v1/super/payouts
     * Build a payout from currently owed transactions.
     * Input: recipient_user_id, currency, [cutoff_date], [notes]
     */
    public function store(Request $request)
    {
        $caller = $this->authSuper();
        if (!$caller) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'recipient_user_id' => 'required|integer|exists:users,id',
            'currency' => 'required|string|size:3',
            'cutoff_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $recipient = User::find($request->recipient_user_id);
        if (!$recipient || !in_array($recipient->role, ['ADMIN', 'MERCHANT'], true)) {
            return response()->json(['message' => 'Invalid recipient.'], 422);
        }

        if (!\App\Http\Controllers\CompanyProfileController::hasBankInfo($recipient)) {
            return response()->json([
                'message' => 'Recipient is missing bank details (bank name, account name, and account number are required).',
                'missing_bank_details' => true,
                'recipient_user_id' => $recipient->id,
            ], 422);
        }

        $currency = strtoupper($request->currency);
        $rows = $this->balances->owedTransactionsFor($recipient, $currency, $request->cutoff_date);
        if ($rows->isEmpty()) {
            return response()->json(['message' => 'Nothing to pay out for this recipient and currency.'], 422);
        }

        $total = $rows->reduce(fn ($carry, $r) => bcadd($carry, $r['amount'], 2), '0.00');
        $earliest = $rows->min(fn ($r) => $r['transaction']->created_at);
        $latest = $rows->max(fn ($r) => $r['transaction']->created_at);

        $payout = DB::transaction(function () use ($caller, $recipient, $currency, $rows, $total, $earliest, $latest, $request) {
            $payout = Payout::create([
                'recipient_user_id' => $recipient->id,
                'recipient_role' => $recipient->role,
                'currency' => $currency,
                'amount' => $total,
                'period_start' => $earliest ? $earliest->toDateString() : null,
                'period_end' => $latest ? $latest->toDateString() : null,
                'status' => 'PENDING',
                'notes' => $request->notes,
                'created_by_user_id' => $caller->id,
            ]);

            foreach ($rows as $r) {
                PayoutTransaction::create([
                    'payout_id' => $payout->id,
                    'transaction_id' => $r['transaction']->id,
                    'source_type' => $r['source_type'],
                    'amount' => $r['amount'],
                ]);
            }
            return $payout;
        });

        return response()->json(['success' => true, 'data' => $payout->fresh('items')], 201);
    }

    /**
     * POST /api/v1/super/payouts/{id}/send
     * Mark a payout as SENT - admin has done the bank transfer.
     */
    public function send(Request $request, $id)
    {
        $caller = $this->authSuper();
        if (!$caller) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $payout = Payout::find($id);
        if (!$payout) {
            return response()->json(['message' => 'Payout not found.'], 404);
        }
        if ($payout->status !== 'PENDING') {
            return response()->json([
                'message' => "Only PENDING payouts can be marked SENT. Current: {$payout->status}.",
            ], 422);
        }

        $payout->update([
            'status' => 'SENT',
            'bank_reference' => $request->bank_reference,
            'notes' => $request->notes ?? $payout->notes,
            'sent_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $payout->fresh()]);
    }

    /**
     * GET /api/v1/super/payouts
     * Filter: recipient_user_id, status, currency, payment_method (filters per
     * transaction inside the payout), start_date, end_date.
     */
    public function index(Request $request)
    {
        if (!$this->authSuper()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = Payout::with('recipient:id,first_name,last_name,email,company_name,role');

        if ($request->filled('recipient_user_id')) $query->where('recipient_user_id', $request->recipient_user_id);
        if ($request->filled('status'))            $query->where('status', strtoupper($request->status));
        if ($request->filled('currency'))          $query->where('currency', strtoupper($request->currency));
        if ($request->filled('start_date'))        $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->filled('end_date'))          $query->whereDate('created_at', '<=', $request->end_date);

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    /**
     * GET /api/v1/super/payouts/{id}
     */
    public function show($id)
    {
        if (!$this->authSuper()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $payout = Payout::with([
            'recipient:id,first_name,last_name,email,company_name,role',
            'items.transaction:id,payment_method,amount,currency,trace,reference,customer_reference,created_at,user_id',
        ])->find($id);

        if (!$payout) {
            return response()->json(['message' => 'Payout not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $payout]);
    }

    /**
     * GET /api/v1/super/payouts/profit-loss
     * Gateway P&L over a date range.
     * Filters: start_date, end_date, currency, payment_method, parent_user_id.
     */
    public function profitLoss(Request $request)
    {
        if (!$this->authSuper()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $baseFilter = function ($q) use ($request) {
            $q->where('status', 'COMPLETED');
            if ($request->filled('currency'))       $q->where('currency', strtoupper($request->currency));
            if ($request->filled('payment_method')) $q->where('payment_method', strtoupper($request->payment_method));
            if ($request->filled('start_date'))    $q->whereDate('created_at', '>=', $request->start_date);
            if ($request->filled('end_date'))      $q->whereDate('created_at', '<=', $request->end_date);
            if ($request->filled('parent_user_id')) {
                $parent = (int) $request->parent_user_id;
                $merchantIds = User::where('role', 'MERCHANT')->where('primary_user', $parent)->pluck('id');
                $q->where(function ($w) use ($parent, $merchantIds) {
                    $w->where('user_id', $parent)->orWhereIn('user_id', $merchantIds);
                });
            }
        };

        $select = [
            'currency',
            'payment_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(CAST(amount AS DECIMAL(15,2))) as total'),
        ];

        $systemRows = Transaction::where('type', 'SYSTEM_CHARGE')->tap($baseFilter)
            ->select($select)->groupBy('currency', 'payment_method')->get();

        $merchantFeeRows = Transaction::where('type', 'MERCHANT_CHARGE')->tap($baseFilter)
            ->select($select)->groupBy('currency', 'payment_method')->get();

        $paymentRows = Transaction::where('type', 'PAYMENT')->tap($baseFilter)
            ->select($select)->groupBy('currency', 'payment_method')->get();

        $totalsByCurrency = function ($rows) {
            $out = [];
            foreach ($rows as $r) {
                $cur = $r->currency ?: 'USD';
                $out[$cur] = bcadd($out[$cur] ?? '0', (string) $r->total, 2);
            }
            return $out;
        };

        return response()->json([
            'success' => true,
            'data' => [
                'gateway_profit' => [
                    'by_currency' => $totalsByCurrency($systemRows),
                    'by_method' => $systemRows,
                ],
                'merchant_fees_to_admin' => [
                    'by_currency' => $totalsByCurrency($merchantFeeRows),
                    'by_method' => $merchantFeeRows,
                ],
                'gross_payments_processed' => [
                    'by_currency' => $totalsByCurrency($paymentRows),
                    'by_method' => $paymentRows,
                ],
            ],
        ]);
    }

    // ----- queries inbox -----

    /**
     * GET /api/v1/super/payouts/messages
     * Inbox of OPEN root messages across all recipients.
     */
    public function inbox(Request $request)
    {
        if (!$this->authSuper()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $status = strtoupper($request->get('status', 'OPEN'));

        $threads = PayoutMessage::whereNull('parent_message_id')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with([
                'sender:id,first_name,last_name,email,role',
                'recipient:id,first_name,last_name,email,company_name,role',
            ])
            ->withCount('replies')
            ->orderByDesc('updated_at')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $threads]);
    }

    /**
     * GET /api/v1/super/payouts/messages/{id}
     */
    public function thread($id)
    {
        if (!$this->authSuper()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $root = PayoutMessage::with([
            'replies.sender:id,first_name,last_name,email,role',
            'sender:id,first_name,last_name,email,role',
            'recipient:id,first_name,last_name,email,company_name,role',
        ])->find($id);

        if (!$root) {
            return response()->json(['message' => 'Thread not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $root]);
    }

    /**
     * POST /api/v1/super/payouts/messages/{id}/reply
     */
    public function reply(Request $request, $id)
    {
        $caller = $this->authSuper();
        if (!$caller) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:1|max:5000',
            'resolve' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $root = PayoutMessage::whereNull('parent_message_id')->find($id);
        if (!$root) {
            return response()->json(['message' => 'Thread not found.'], 404);
        }

        $reply = PayoutMessage::create([
            'payout_id' => $root->payout_id,
            'recipient_user_id' => $root->recipient_user_id,
            'sender_user_id' => $caller->id,
            'sender_role' => $caller->role,
            'subject' => $root->subject,
            'body' => $request->body,
            'parent_message_id' => $root->id,
            'status' => 'OPEN',
        ]);

        $rootUpdate = ['updated_at' => now()];
        if ($request->boolean('resolve')) {
            $rootUpdate['status'] = 'RESOLVED';
        }
        $root->update($rootUpdate);

        return response()->json(['success' => true, 'data' => $reply], 201);
    }

    // ============ SCHEDULES ============

    public function listSchedules(Request $request)
    {
        if (!$this->authSuper()) return response()->json(['message' => 'Forbidden.'], 403);

        $rows = PayoutSchedule::with('recipient:id,first_name,last_name,email,company_name,role')
            ->orderByDesc('is_active')
            ->orderBy('cadence')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeSchedule(Request $request)
    {
        $caller = $this->authSuper();
        if (!$caller) return response()->json(['message' => 'Forbidden.'], 403);

        $payload = $this->validateScheduleInput($request);
        if (is_array($payload) && isset($payload['error_response'])) {
            return $payload['error_response'];
        }

        $schedule = PayoutSchedule::create(array_merge($payload, [
            'created_by_user_id' => $caller->id,
            'is_active' => $request->boolean('is_active', true),
        ]));

        return response()->json(['success' => true, 'data' => $schedule], 201);
    }

    public function updateSchedule(Request $request, $id)
    {
        if (!$this->authSuper()) return response()->json(['message' => 'Forbidden.'], 403);

        $schedule = PayoutSchedule::find($id);
        if (!$schedule) return response()->json(['message' => 'Schedule not found.'], 404);

        $payload = $this->validateScheduleInput($request);
        if (is_array($payload) && isset($payload['error_response'])) {
            return $payload['error_response'];
        }

        if ($request->has('is_active')) {
            $payload['is_active'] = $request->boolean('is_active');
        }

        $schedule->update($payload);

        return response()->json(['success' => true, 'data' => $schedule->fresh()]);
    }

    public function deleteSchedule($id)
    {
        if (!$this->authSuper()) return response()->json(['message' => 'Forbidden.'], 403);

        $schedule = PayoutSchedule::find($id);
        if (!$schedule) return response()->json(['message' => 'Schedule not found.'], 404);

        $schedule->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Manual trigger - lets the super-admin run a schedule "now" without
     * waiting for the cron. Useful for testing and for one-off catch-up runs.
     */
    public function runScheduleNow($id)
    {
        if (!$this->authSuper()) return response()->json(['message' => 'Forbidden.'], 403);

        $schedule = PayoutSchedule::find($id);
        if (!$schedule) return response()->json(['message' => 'Schedule not found.'], 404);

        $runner = app(\App\Services\ScheduledPayoutRunner::class);
        $summary = $runner->runOne($schedule, force: true);

        return response()->json(['success' => true, 'data' => $summary]);
    }

    private function validateScheduleInput(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipient_user_id' => 'nullable|integer|exists:users,id',
            'recipient_role_scope' => 'nullable|in:ADMIN,MERCHANT',
            'currency' => 'required|string|size:3',
            'cadence' => 'required|in:DAILY,WEEKLY,MONTHLY',
            'day_of_week' => 'nullable|integer|min:1|max:7',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'minimum_amount' => 'nullable|numeric|min:0',
            'cutoff_hours_back' => 'nullable|integer|min:0|max:720',
            'default_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ['error_response' => response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422)];
        }

        $cadence = strtoupper($request->cadence);
        if ($cadence === 'WEEKLY' && !$request->filled('day_of_week')) {
            return ['error_response' => response()->json(['message' => 'day_of_week is required for WEEKLY cadence.'], 422)];
        }
        if ($cadence === 'MONTHLY' && !$request->filled('day_of_month')) {
            return ['error_response' => response()->json(['message' => 'day_of_month is required for MONTHLY cadence.'], 422)];
        }

        return [
            'recipient_user_id' => $request->recipient_user_id,
            'recipient_role_scope' => $request->recipient_role_scope,
            'currency' => strtoupper($request->currency),
            'cadence' => $cadence,
            'day_of_week' => $cadence === 'WEEKLY' ? (int) $request->day_of_week : null,
            'day_of_month' => $cadence === 'MONTHLY' ? (int) $request->day_of_month : null,
            'minimum_amount' => $request->filled('minimum_amount') ? (float) $request->minimum_amount : 0,
            'cutoff_hours_back' => (int) $request->get('cutoff_hours_back', 24),
            'default_notes' => $request->default_notes,
        ];
    }
}
