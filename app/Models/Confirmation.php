<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Confirmation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'token',
        'user_id',
    ];

    /**
     * Get the user associated with the confirmation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new confirmation token.
     *
     * @return void
     */
    public function generateToken(): void
    {
        $this->token = bin2hex(random_bytes(16)); // Generate a random token
    }
}
