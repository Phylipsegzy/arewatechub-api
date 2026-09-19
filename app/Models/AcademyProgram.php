<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademyProgram extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function batches()
    {
        return $this->hasMany(AcademyBatch::class);
    }
}
