<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademyEnrollment extends Model
{
    protected $fillable = [
        'academy_batch_id', 'customer_id', 'track_selected', 'programme_selected',
        'bootcamp_option', 'status_type', 'tuition_tier', 'bootcamp_fee', 'tuition_fee',
        'amount_due', 'amount_paid', 'reference', 'application_status', 'payment_status',
        'motivation', 'state_code', 'matric_number', 'education_level', 'has_laptop',
    ];

    protected $casts = ['has_laptop' => 'boolean'];

    public function batch()
    {
        return $this->belongsTo(AcademyBatch::class, 'academy_batch_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
