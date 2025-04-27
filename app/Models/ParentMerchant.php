<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class ParentMerchant extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'parent_merchants';

    protected $fillable = [
        'merchant_name',
        'email',
        'company_name',
        'merchant_description',
        'return_url',
        'status',
        'merchant_key',
        'merchant_secret'
    ];

    protected $hidden = [
        'password',
        'merchant_secret',
        'remember_token',
    ];

    protected $casts = [
        'status' => 'boolean',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'volume',
        'transaction_count',
        'success_rate'
    ];

    public function transactions()
    {
        return $this->hasMany(SystemTransaction::class, 'merchant_id');
    }

    public function getVolumeAttribute()
    {
        return $this->transactions()
                    ->where('status', SystemTransaction::STATUS_COMPLETED)
                    ->sum('amount');
    }

    public function getTransactionCountAttribute()
    {
        return $this->transactions()->count();
    }

    public function getSuccessRateAttribute()
    {
        $total = $this->transaction_count;
        if ($total === 0) return 0;

        $successful = $this->transactions()
                          ->where('status', SystemTransaction::STATUS_COMPLETED)
                          ->count();

        return round(($successful / $total) * 100, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    protected static function booted()
    {
        static::creating(function ($merchant) {
            if (!$merchant->merchant_key) {
                $merchant->merchant_key = 'MK-' . strtoupper(uniqid());
            }
            if (!$merchant->merchant_secret) {
                $merchant->merchant_secret = hash('sha256', uniqid() . time() . $merchant->email);
            }
        });
    }
}
