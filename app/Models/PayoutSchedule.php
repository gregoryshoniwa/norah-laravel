<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutSchedule extends Model
{
    protected $fillable = [
        'recipient_user_id',
        'recipient_role_scope',
        'currency',
        'cadence',
        'day_of_week',
        'day_of_month',
        'minimum_amount',
        'cutoff_hours_back',
        'default_notes',
        'is_active',
        'last_run_at',
        'last_run_summary',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'minimum_amount' => 'decimal:2',
        'last_run_at' => 'datetime',
        'last_run_summary' => 'array',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Should this schedule fire today? Lightweight: caller is expected to be
     * the daily scheduler, so DAILY always fires; WEEKLY/MONTHLY check the
     * current day.
     */
    public function shouldRunOn(\Carbon\CarbonInterface $when): bool
    {
        if (!$this->is_active) return false;

        return match (strtoupper($this->cadence)) {
            'DAILY' => true,
            'WEEKLY' => $this->day_of_week !== null && $when->dayOfWeekIso === (int) $this->day_of_week,
            'MONTHLY' => $this->day_of_month !== null && $when->day === (int) $this->day_of_month,
            default => false,
        };
    }
}
