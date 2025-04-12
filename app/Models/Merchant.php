<?php

namespace App\Models;

use App\Models\User;
use App\Models\Charge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    use HasFactory;

    protected $primaryKey = 'merchant_id'; // Specify the primary key

    public $incrementing = false; // If merchant_id is not auto-incrementing
    protected $keyType = 'string'; // If merchant_id is a string (e.g., UUID)


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'merchant_name',
        'merchant_address',
        'merchant_phone',
        'merchant_email',
        'label',
        'merchant_uid',
        'merchant_secret',
        'merchant_status',
        'merchant_country',
        'merchant_city',
        'merchant_logo',
        'merchant_website',
        'merchant_description',
        'return_url',
        'web_service_url',
        'user_id',
    ];

    /**
     * Get the user associated with the merchant.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the charges associated with the merchant.
     */
    public function charges()
    {
        return $this->belongsToMany(Charge::class, 'merchant_user_name');
    }
}
