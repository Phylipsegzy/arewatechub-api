<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CohortProgram extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function intakes()
    {
        return $this->hasMany(CohortIntake::class);
    }
}
