<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['provider', 'mode', 'secret_key', 'public_key'];
}
