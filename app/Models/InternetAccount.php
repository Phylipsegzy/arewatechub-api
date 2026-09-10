<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternetAccount extends Model
{
    protected $fillable = ['username', 'password', 'duration_type', 'status', 'last_assigned'];
}
