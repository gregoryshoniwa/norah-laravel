<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'primary_user',
        'company_name',
        'address',
        'public_email',
        'phone',
        'description',
        'website',
        'logo',
        'is_activated',
        'user_secret',
        'return_url',
        'web_service_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'user_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_activated' => 'boolean',
    ];

    /**
     * Get the user's role as a string.
     *
     * @return string
     */
    public function getRoleAttribute(): string
    {
        return $this->attributes['role'];
    }

    /**
     * Check if the account is activated.
     *
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->is_activated;
    }

    /**
     * Check if the account is non-expired.
     *
     * @return bool
     */
    public function isAccountNonExpired(): bool
    {
        return true;
    }

    /**
     * Check if the account is non-locked.
     *
     * @return bool
     */
    public function isAccountNonLocked(): bool
    {
        return true;
    }

    /**
     * Check if the credentials are non-expired.
     *
     * @return bool
     */
    public function isCredentialsNonExpired(): bool
    {
        return true;
    }

    /**
     * Check if the account is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true;
    }

     // Add these methods for JWT
     public function getJWTIdentifier()
     {
         return $this->getKey();
     }

     public function getJWTCustomClaims()
     {
        return [
            'user_id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
        ];
     }
}
