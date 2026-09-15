<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CohortIntake extends Model
{
    protected $fillable = ['cohort_program_id', 'name', 'start_date', 'end_date', 'capacity'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function program()
    {
        return $this->belongsTo(CohortProgram::class, 'cohort_program_id');
    }

    public function enrollments()
    {
        return $this->hasMany(CohortEnrollment::class);
    }

    public function slotsTaken(): int
    {
        return $this->enrollments()->count();
    }

    public function slotsRemaining(): int
    {
        return max($this->capacity - $this->slotsTaken(), 0);
    }
}
