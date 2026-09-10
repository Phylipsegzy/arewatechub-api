<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDedicatedAccount extends Model
{
    protected $fillable = [
        'customer_id', 'paystack_customer_id', 'bank_name', 'account_name',
        'account_number', 'currency', 'paystack_account_id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
