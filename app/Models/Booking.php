<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'customer_id', 'plan_id', 'plan_duration_id', 'workspace_session_id', 'room_id',
        'seat_number', 'price', 'status', 'start_date', 'end_date', 'start_datetime', 'end_datetime',
        'survey_sent_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function workspaceSession()
    {
        return $this->belongsTo(WorkspaceSession::class);
    }

    public function order()
    {
        return $this->morphOne(Order::class, 'orderable');
    }

    public function internetAccess()
    {
        return $this->hasOne(UserInternetAccess::class)->with('internetAccount');
    }

    public function feedback()
    {
        return $this->hasOne(BookingFeedback::class);
    }
}
