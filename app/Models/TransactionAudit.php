<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'trace',
        'reference',
        'payment_method',
        'stage',
        'event',
        'level',
        'provider',
        'endpoint',
        'status_code',
        'request_payload',
        'response_payload',
        'meta_data',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'meta_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

