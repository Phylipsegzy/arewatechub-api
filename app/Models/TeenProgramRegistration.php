<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeenProgramRegistration extends Model
{
    protected $fillable = [
        'customer_id', 'child_firstname', 'child_lastname', 'child_age', 'child_gender',
        'school', 'parent_name', 'parent_phone', 'parent_address', 'nearest_landmark',
        'relationship', 'registration_amount', 'registration_payment_status',
        'vip_requested', 'vip_amount', 'vip_payment_status',
    ];

    protected $casts = [
        'vip_requested' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(TeenProgramPayment::class, 'registration_id');
    }
}
