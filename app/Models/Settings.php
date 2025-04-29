<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'settings_data'
    ];

    /**
     * Get the user that owns the settings.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
