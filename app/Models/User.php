<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['username', 'password', 'role', 'display_name'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'hashed'];
}
