<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionTimeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'title',
        'description',
        'icon',
        'meta_data'
    ];

    protected $casts = [
        'meta_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
