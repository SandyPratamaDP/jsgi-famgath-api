<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'username', 'status',
        'ip_address', 'user_agent',
        'city', 'region', 'country', 'isp',
        'logged_at',
    ];

    protected $casts = ['logged_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
