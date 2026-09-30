<?php

namespace App\Console\Commands;

use App\Http\Controllers\TransactionController;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Safety net for EcoCash payments whose outcome was never collected.
 *
 * The hosted checkout finalizes by polling from the browser, and the direct
 * API finalizes from the merchant's poll or from EcoCash's notify callback.
 * If none of those arrive (client crashed, callback lost), the CONFIRM row
 * stays PENDING forever and never reaches the dashboards, payouts or the
 * merchant webhook. This command re-queries EcoCash for those rows and runs
 * the normal finalization path.
 */
class ReconcilePendingEcocash extends Command
{
    protected $signature = 'ecocash:reconcile-pending
                            {--min-age=2 : Only look at rows older than this many minutes}
                            {--max-age=1440 : Ignore rows older than this many minutes (default 24h)}
                            {--limit=100 : Maximum rows to process in one run}';

    protected $description = 'Re-query EcoCash for PENDING ECOCASH transactions and finalize any that have completed or failed.';

    public function handle(TransactionController $transactions): int
    {
        $rows = Transaction::query()
            ->where('payment_method', 'ECOCASH')
            ->where('type', 'CONFIRM')
            ->where('status', 'PENDING')
            ->whereNotNull('reference')
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('min-age')))
            ->where('created_at', '>=', now()->subMinutes((int) $this->option('max-age')))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No pending EcoCash transactions to reconcile.');
            return self::SUCCESS;
        }

        $summary = ['COMPLETED' => 0, 'FAILED' => 0, 'PENDING' => 0, 'ERROR' => 0];

        foreach ($rows as $row) {
            try {
                $response = $transactions->checkTransactionStatus(new Request(['trace' => $row->trace]));
                $status = $response->getData(true)['status'] ?? 'PENDING';
                $summary[$status] = ($summary[$status] ?? 0) + 1;
                $this->line("#{$row->id} {$row->trace} -> {$status}");
            } catch (\Throwable $e) {
                $summary['ERROR']++;
                $this->error("#{$row->id} {$row->trace} -> error: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info(sprintf(
            'Reconciled %d rows: %d completed, %d failed, %d still pending, %d errors.',
            $rows->count(),
            $summary['COMPLETED'],
            $summary['FAILED'],
            $summary['PENDING'],
            $summary['ERROR']
        ));

        return self::SUCCESS;
    }
}
