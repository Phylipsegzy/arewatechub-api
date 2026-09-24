<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'email', 'password', 'role', 'created_by'];
    protected $hidden = ['password'];
    protected $casts = ['password' => 'hashed'];

    public function isFullAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
