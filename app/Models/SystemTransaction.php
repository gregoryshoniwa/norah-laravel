<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemTransaction extends Model
{
    use HasFactory;

    protected $table = 'system_transactions';

    protected $fillable = [
        'reference',
        'amount',
        'charge',
        'merchant_charge',
        'currency',
        'type',
        'status',
        'customer_name',
        'customer_email',
        'merchant_id',
        'user_id',
        'meta_data'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'charge' => 'decimal:2',
        'merchant_charge' => 'decimal:2',
        'meta_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    const STATUS_PENDING = 'PENDING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_FAILED = 'FAILED';

    public function merchant()
    {
        return $this->belongsTo(ParentMerchant::class, 'merchant_id');
    }

    public function user()
    {
        return $this->belongsTo(SystemUser::class, 'user_id');
    }

    public function timelines()
    {
        return $this->hasMany(SystemTransactionTimeline::class, 'transaction_id')
                    ->orderBy('created_at', 'desc');
    }

    protected static function booted()
    {
        static::creating(function ($transaction) {
            if (!$transaction->reference) {
                $transaction->reference = 'TXN-' . strtoupper(uniqid());
            }
        });

        static::created(function ($transaction) {
            // Create initial timeline entry
            $transaction->timelines()->create([
                'title' => 'Transaction Initiated',
                'description' => 'Transaction created with reference ' . $transaction->reference,
                'icon' => 'ri-play-circle-line'
            ]);
        });

        static::updated(function ($transaction) {
            if ($transaction->wasChanged('status')) {
                $icon = match($transaction->status) {
                    self::STATUS_COMPLETED => 'ri-check-line',
                    self::STATUS_FAILED => 'ri-close-circle-line',
                    default => 'ri-time-line'
                };

                $transaction->timelines()->create([
                    'title' => 'Status Updated',
                    'description' => 'Transaction status changed to ' . $transaction->status,
                    'icon' => $icon
                ]);
            }
        });
    }
}
