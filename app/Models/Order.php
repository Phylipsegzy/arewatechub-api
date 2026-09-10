<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id', 'orderable_type', 'orderable_id', 'amount',
        'payment_method', 'reference', 'paystack_reference', 'status', 'proof_of_payment',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderable()
    {
        return $this->morphTo();
    }
}
