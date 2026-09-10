<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingFeedback extends Model
{
    protected $table = 'booking_feedback';

    protected $fillable = ['booking_id', 'customer_id', 'rating', 'comment'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
