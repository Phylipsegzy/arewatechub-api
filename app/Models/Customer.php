<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'firstname', 'lastname', 'middlename', 'email', 'phone',
        'password', 'state', 'city', 'gender', 'address', 'dob', 'education',
        'wallet_balance',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'wallet_balance' => 'decimal:2',
        'password' => 'hashed',
    ];

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function dedicatedAccount()
    {
        return $this->hasOne(CustomerDedicatedAccount::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
