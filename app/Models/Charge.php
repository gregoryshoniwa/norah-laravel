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

    public function merchant()
    {
        return $this->hasOneThrough(
            Merchant::class,
            User::class,
            'email',        // Foreign key on the users table (email)
            'user_id',      // Foreign key on the merchants table (user_id)
            'merchant_user_name', // Local key on the charges table (merchant_user_name)
            'id'            // Local key on the users table (id)
        );
    }
}
