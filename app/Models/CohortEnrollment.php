<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CohortEnrollment extends Model
{
    protected $fillable = [
        'cohort_intake_id', 'customer_id', 'track_selected', 'programme_selected',
        'bootcamp_option', 'status_type', 'tuition_tier', 'bootcamp_fee', 'tuition_fee',
        'amount_due', 'amount_paid', 'reference', 'application_status', 'payment_status',
        'motivation', 'state_code', 'matric_number', 'education_level', 'has_laptop',
        'certificate_status', 'certificate_file',
    ];

    protected $casts = ['has_laptop' => 'boolean'];

    public function intake()
    {
        return $this->belongsTo(CohortIntake::class, 'cohort_intake_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
