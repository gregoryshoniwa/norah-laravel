<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'charge_type',
        'charge_source',
        'charge_category',
        'status',
        'currency',
        'value',
        'statement_narration',
        'min_threshold',
        'max_threshold',
        'pl_account',
        'merchant_user_id',
        'merchant_user_name',
        'deleted',
    ];

    /**
     * Scope to filter non-deleted charges.
     */
    public function scopeActive($query)
    {
        return $query->where('deleted', false);
    }

    /**
     * The primary link is to the merchant's User row. The Merchant business
     * record can be reached via that user's hasOne relationship.
     */
    public function merchantUser()
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }
}
