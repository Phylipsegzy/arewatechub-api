<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['name', 'seat_start', 'seat_end'];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
