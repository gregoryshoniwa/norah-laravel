<?php

namespace App\Console\Commands;

use App\Services\ScheduledPayoutRunner;
use Illuminate\Console\Command;

class RunScheduledPayouts extends Command
{
    protected $signature = 'payouts:run-scheduled {--id= : Only run a specific schedule id (forced)}';

    protected $description = 'Process active payout schedules due now. Creates PENDING payouts for eligible recipients.';

    public function handle(ScheduledPayoutRunner $runner): int
    {
        if ($this->option('id')) {
            $schedule = \App\Models\PayoutSchedule::find($this->option('id'));
            if (!$schedule) {
                $this->error('Schedule not found.');
                return self::FAILURE;
            }
            $summary = $runner->runOne($schedule, force: true);
            $this->info(json_encode($summary, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $summaries = $runner->runAllDue();
        if (empty($summaries)) {
            $this->info('No schedules due now.');
            return self::SUCCESS;
        }

        foreach ($summaries as $s) {
            $this->info("Schedule #{$s['schedule_id']} - created {$s['payouts_created']} payouts totalling {$s['currency']} {$s['total_created_amount']}");
        }

        return self::SUCCESS;
    }
}
