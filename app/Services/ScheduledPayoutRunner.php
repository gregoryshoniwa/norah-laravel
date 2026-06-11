<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\PayoutSchedule;
use App\Models\PayoutTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs auto-scheduled payouts.
 *
 *  - For each active schedule due today, find the recipients in scope
 *    (single user, role bucket, or everyone) and build a PENDING payout
 *    per recipient when their balance >= the minimum threshold.
 *  - Records a per-run summary on the schedule so the admin UI can show
 *    "last run created N payouts totalling X".
 *
 * Idempotency: each transaction can only be in one payout (DB unique index).
 * If the runner crashes mid-cycle, retrying it will skip recipients that
 * were already paid out in the same window.
 */
class ScheduledPayoutRunner
{
    public function __construct(protected PayoutBalanceService $balances) {}

    /**
     * Iterate every active schedule and run the ones due now.
     * Returns a per-schedule summary.
     */
    public function runAllDue(?Carbon $now = null): array
    {
        $now = $now ?: now();
        $results = [];

        $schedules = PayoutSchedule::where('is_active', true)->get();
        foreach ($schedules as $schedule) {
            if (!$schedule->shouldRunOn($now)) {
                continue;
            }
            $results[] = $this->runOne($schedule, force: false, now: $now);
        }

        return $results;
    }

    /**
     * Run a single schedule. If $force is true, the cadence check is skipped
     * (used by "Run now" button in the admin UI).
     */
    public function runOne(PayoutSchedule $schedule, bool $force = false, ?Carbon $now = null): array
    {
        $now = $now ?: now();

        if (!$force && !$schedule->shouldRunOn($now)) {
            return [
                'schedule_id' => $schedule->id,
                'skipped' => true,
                'reason' => 'Not due today',
            ];
        }

        $recipients = $this->recipientsFor($schedule);
        $cutoff = $now->copy()->subHours((int) $schedule->cutoff_hours_back);

        $created = 0;
        $skipped = 0;
        $total = '0.00';
        $errors = [];

        foreach ($recipients as $user) {
            try {
                // Skip recipients with no bank info - they can't actually be paid.
                if (!\App\Http\Controllers\CompanyProfileController::hasBankInfo($user)) {
                    Log::warning('ScheduledPayoutRunner skipping recipient with no bank info', [
                        'schedule_id' => $schedule->id,
                        'recipient_user_id' => $user->id,
                    ]);
                    $errors[] = ['recipient_user_id' => $user->id, 'message' => 'Missing bank details'];
                    $skipped++;
                    continue;
                }

                $rows = $this->balances->owedTransactionsFor($user, $schedule->currency, $cutoff->toDateString());
                if ($rows->isEmpty()) { $skipped++; continue; }

                $amount = $rows->reduce(fn ($c, $r) => bcadd($c, $r['amount'], 2), '0.00');
                if (bccomp($amount, (string) $schedule->minimum_amount, 2) < 0) {
                    $skipped++;
                    continue;
                }

                DB::transaction(function () use ($schedule, $user, $rows, $amount) {
                    $payout = Payout::create([
                        'recipient_user_id' => $user->id,
                        'recipient_role' => $user->role,
                        'currency' => $schedule->currency,
                        'amount' => $amount,
                        'period_start' => optional($rows->min(fn ($r) => $r['transaction']->created_at))->toDateString(),
                        'period_end' => optional($rows->max(fn ($r) => $r['transaction']->created_at))->toDateString(),
                        'status' => 'PENDING',
                        'notes' => $schedule->default_notes
                            ? $schedule->default_notes . " (auto-scheduled #{$schedule->id})"
                            : "Auto-created by schedule #{$schedule->id}",
                        'created_by_user_id' => $schedule->created_by_user_id,
                    ]);
                    foreach ($rows as $r) {
                        PayoutTransaction::create([
                            'payout_id' => $payout->id,
                            'transaction_id' => $r['transaction']->id,
                            'source_type' => $r['source_type'],
                            'amount' => $r['amount'],
                        ]);
                    }
                });

                $created++;
                $total = bcadd($total, $amount, 2);
            } catch (\Throwable $e) {
                Log::error('ScheduledPayoutRunner failed for recipient', [
                    'schedule_id' => $schedule->id,
                    'recipient_user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
                $errors[] = ['recipient_user_id' => $user->id, 'message' => $e->getMessage()];
            }
        }

        $summary = [
            'schedule_id' => $schedule->id,
            'ran_at' => $now->toIso8601String(),
            'forced' => $force,
            'recipients_considered' => $recipients->count(),
            'payouts_created' => $created,
            'recipients_skipped' => $skipped,
            'total_created_amount' => $total,
            'currency' => $schedule->currency,
            'errors' => $errors,
        ];

        $schedule->update([
            'last_run_at' => $now,
            'last_run_summary' => $summary,
        ]);

        return $summary;
    }

    /**
     * Resolve the set of users this schedule applies to.
     */
    protected function recipientsFor(PayoutSchedule $schedule)
    {
        if ($schedule->recipient_user_id) {
            $u = User::find($schedule->recipient_user_id);
            return $u ? collect([$u]) : collect();
        }

        $query = User::whereIn('role', ['ADMIN', 'MERCHANT']);
        if ($schedule->recipient_role_scope) {
            $query->where('role', $schedule->recipient_role_scope);
        }
        return $query->get();
    }
}
