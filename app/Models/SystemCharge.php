<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemCharge extends Model
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
        'user_email',
        'deleted',
    ];

    /**
     * Scope to filter non-deleted system charges.
     */
    public function scopeActive($query)
    {
        return $query->where('deleted', false);
    }

    /**
     * Relationship with the User model using the user's email.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_email', 'email');
    }
}
