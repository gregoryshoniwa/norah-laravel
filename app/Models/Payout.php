<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    protected $fillable = [
        'recipient_user_id',
        'recipient_role',
        'currency',
        'amount',
        'period_start',
        'period_end',
        'status',
        'bank_reference',
        'notes',
        'created_by_user_id',
        'sent_at',
        'confirmed_at',
        'disputed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'disputed_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutTransaction::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PayoutMessage::class);
    }
}
