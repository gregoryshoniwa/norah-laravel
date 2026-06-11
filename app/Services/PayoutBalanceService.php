<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\PayoutTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralises the "what does this user actually own?" math.
 *
 * Money model agreed with stakeholder:
 *  - SUPER (gateway) keeps SYSTEM_CHARGE - their profit.
 *  - ADMIN (parent company) keeps MERCHANT_CHARGE collected by their
 *    sub-merchants, PLUS PAYMENT.amount from transactions they took directly
 *    (user_type = 'U').
 *  - MERCHANT (sub-merchant) keeps PAYMENT.amount only.
 *
 * A transaction is "owed" once it is COMPLETED. It's removed from the owed
 * balance the moment it's added to any payout (regardless of payout status)
 * so it can never be paid out twice.
 */
class PayoutBalanceService
{
    /**
     * Compute the unpaid balance + per-method breakdown owed to a user.
     * Returns an array keyed by currency.
     */
    public function expectedFor(User $user): array
    {
        if ($user->role === 'MERCHANT') {
            return $this->merchantBreakdown($user);
        }
        if ($user->role === 'ADMIN') {
            return $this->adminBreakdown($user);
        }
        return ['by_currency' => []];
    }

    /**
     * The full set of currently-owed transactions for a user, with the source
     * amount (PAYMENT.amount for merchants, the appropriate slice for admins).
     * Used by the super-admin when building a payout - it needs the rows that
     * will be attached to it.
     *
     * @return \Illuminate\Support\Collection<int, array{transaction: Transaction, source_type: string, amount: string}>
     */
    public function owedTransactionsFor(User $user, ?string $currency = null, ?string $cutoff = null)
    {
        if ($user->role === 'MERCHANT') {
            return $this->merchantOwedRows($user, $currency, $cutoff);
        }
        if ($user->role === 'ADMIN') {
            return $this->adminOwedRows($user, $currency, $cutoff);
        }
        return collect();
    }

    // ----- MERCHANT -----

    private function merchantBreakdown(User $merchant): array
    {
        $alreadyPaid = $this->paidTransactionIds('PAYMENT');

        $rows = Transaction::query()
            ->where('type', 'PAYMENT')
            ->where('status', 'COMPLETED')
            ->where('user_id', $merchant->id)
            ->whereNotIn('id', $alreadyPaid)
            ->select(
                'currency',
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CAST(amount AS DECIMAL(15,2))) as total')
            )
            ->groupBy('currency', 'payment_method')
            ->get();

        return [
            'by_currency' => $this->collapseByCurrency($rows),
        ];
    }

    private function merchantOwedRows(User $merchant, ?string $currency, ?string $cutoff)
    {
        $q = Transaction::query()
            ->where('type', 'PAYMENT')
            ->where('status', 'COMPLETED')
            ->where('user_id', $merchant->id)
            ->whereNotIn('id', $this->paidTransactionIds('PAYMENT'));

        if ($currency) $q->where('currency', strtoupper($currency));
        if ($cutoff)   $q->whereDate('created_at', '<=', $cutoff);

        return $q->orderBy('created_at')->get()->map(fn ($t) => [
            'transaction' => $t,
            'source_type' => 'PAYMENT',
            'amount' => (string) $t->amount,
        ]);
    }

    // ----- ADMIN -----

    private function adminBreakdown(User $admin): array
    {
        // Two streams contribute to an admin's balance.
        //  (a) Their OWN direct payments (user_type='U', user_id=admin)
        $directRows = Transaction::query()
            ->where('type', 'PAYMENT')
            ->where('status', 'COMPLETED')
            ->where('user_type', 'U')
            ->where('user_id', $admin->id)
            ->whereNotIn('id', $this->paidTransactionIds('PAYMENT'))
            ->select(
                'currency',
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CAST(amount AS DECIMAL(15,2))) as total')
            )
            ->groupBy('currency', 'payment_method')
            ->get();

        //  (b) MERCHANT_CHARGE rows from any sub-merchant whose primary_user = admin.
        $subMerchantIds = User::where('role', 'MERCHANT')
            ->where('primary_user', $admin->id)
            ->pluck('id');

        $feeRows = Transaction::query()
            ->where('type', 'MERCHANT_CHARGE')
            ->where('status', 'COMPLETED')
            ->whereIn('user_id', $subMerchantIds)
            ->whereNotIn('id', $this->paidTransactionIds('MERCHANT_CHARGE'))
            ->select(
                'currency',
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CAST(amount AS DECIMAL(15,2))) as total')
            )
            ->groupBy('currency', 'payment_method')
            ->get();

        return [
            'by_currency' => $this->collapseByCurrency($directRows, $feeRows),
        ];
    }

    private function adminOwedRows(User $admin, ?string $currency, ?string $cutoff)
    {
        $direct = Transaction::query()
            ->where('type', 'PAYMENT')
            ->where('status', 'COMPLETED')
            ->where('user_type', 'U')
            ->where('user_id', $admin->id)
            ->whereNotIn('id', $this->paidTransactionIds('PAYMENT'));

        $subMerchantIds = User::where('role', 'MERCHANT')
            ->where('primary_user', $admin->id)
            ->pluck('id');

        $fees = Transaction::query()
            ->where('type', 'MERCHANT_CHARGE')
            ->where('status', 'COMPLETED')
            ->whereIn('user_id', $subMerchantIds)
            ->whereNotIn('id', $this->paidTransactionIds('MERCHANT_CHARGE'));

        if ($currency) { $direct->where('currency', strtoupper($currency)); $fees->where('currency', strtoupper($currency)); }
        if ($cutoff)   { $direct->whereDate('created_at', '<=', $cutoff);   $fees->whereDate('created_at', '<=', $cutoff); }

        $a = $direct->orderBy('created_at')->get()->map(fn ($t) => [
            'transaction' => $t, 'source_type' => 'PAYMENT', 'amount' => (string) $t->amount,
        ]);
        $b = $fees->orderBy('created_at')->get()->map(fn ($t) => [
            'transaction' => $t, 'source_type' => 'MERCHANT_CHARGE', 'amount' => (string) $t->amount,
        ]);

        return $a->concat($b);
    }

    // ----- helpers -----

    /** All transaction IDs already attached to a payout for a given source. */
    private function paidTransactionIds(string $sourceType)
    {
        return PayoutTransaction::where('source_type', $sourceType)->pluck('transaction_id');
    }

    /**
     * Collapse one or more SUM-grouped result sets into:
     *   ['USD' => ['total' => '12.34', 'by_method' => [ ['payment_method'=>'VISA_MASTER','count'=>3,'total'=>'10.00'], ... ]]]
     */
    private function collapseByCurrency(...$resultSets): array
    {
        $byCurrency = [];

        foreach ($resultSets as $rows) {
            foreach ($rows as $r) {
                $cur = $r->currency ?: 'USD';
                if (!isset($byCurrency[$cur])) {
                    $byCurrency[$cur] = ['currency' => $cur, 'total' => '0.00', 'by_method' => []];
                }
                $byCurrency[$cur]['total'] = bcadd($byCurrency[$cur]['total'], (string) $r->total, 2);

                $method = $r->payment_method ?: 'UNKNOWN';
                $existing = collect($byCurrency[$cur]['by_method'])->firstWhere('payment_method', $method);
                if ($existing) {
                    foreach ($byCurrency[$cur]['by_method'] as &$m) {
                        if ($m['payment_method'] === $method) {
                            $m['count'] += (int) $r->count;
                            $m['total'] = bcadd($m['total'], (string) $r->total, 2);
                        }
                    }
                    unset($m);
                } else {
                    $byCurrency[$cur]['by_method'][] = [
                        'payment_method' => $method,
                        'count' => (int) $r->count,
                        'total' => bcadd('0', (string) $r->total, 2),
                    ];
                }
            }
        }

        return array_values($byCurrency);
    }
}
