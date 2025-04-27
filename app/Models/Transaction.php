<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_method',
        'merchant_uid',
        'user_id',
        'status',
        'error_message',
        'display_error_message',
        'reference',
        'debit_reference',
        'credit_reference',
        'response_code',
        'trace',
        'statement_narration',
        'type',
        'user_type',
        'currency',
        'amount',
        'charge',
        'numeric_amount',
        'additional_data',
        'request',
        'response',
        'user_name',
        'application_id',
        'transaction_status',
        'reference_code',
        'parent_transaction_id',
        'deleted',
    ];

    /**
     * Get the user associated with the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
