<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeenProgramPayment extends Model
{
    protected $fillable = [
        'customer_id', 'registration_id', 'payment_type', 'amount', 'reference', 'status',
    ];

    public function registration()
    {
        return $this->belongsTo(TeenProgramRegistration::class, 'registration_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
