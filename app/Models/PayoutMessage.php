<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutMessage extends Model
{
    protected $fillable = [
        'payout_id',
        'recipient_user_id',
        'sender_user_id',
        'sender_role',
        'subject',
        'body',
        'parent_message_id',
        'status',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PayoutMessage::class, 'parent_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PayoutMessage::class, 'parent_message_id');
    }
}
