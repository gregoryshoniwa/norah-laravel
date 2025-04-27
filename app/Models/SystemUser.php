<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class SystemUser extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'system_users';

    protected $fillable = [
        'fullName',
        'email',
        'password',
        'role',
        'status',
        'notes',
        'last_login_at',
        'last_login_ip'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token'
    ];

    protected $casts = [
        'status' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    const ROLE_ADMIN = 'ADMIN';
    const ROLE_USER = 'USER';

    public function transactions()
    {
        return $this->hasMany(SystemTransaction::class, 'user_id');
    }

    public function getInitialsAttribute()
    {
        $names = explode(' ', $this->fullName);
        return strtoupper(array_reduce($names, function($initials, $name) {
            return $initials . substr($name, 0, 1);
        }, ''));
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    public function scopeAdmin($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeRegularUser($query)
    {
        return $query->where('role', self::ROLE_USER);
    }

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->api_token) {
                $user->api_token = hash('sha256', uniqid() . time() . $user->email);
            }
        });
    }
}
