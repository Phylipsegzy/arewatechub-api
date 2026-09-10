<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'requires_seat_selection', 'is_automatic_daily',
        'promo_fixed_end_date', 'restricted_weekday',
    ];

    protected $casts = [
        'promo_fixed_end_date' => 'date',
    ];

    public function durations()
    {
        return $this->hasMany(PlanDuration::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'plan_rooms');
    }

    public function sessions()
    {
        return $this->belongsToMany(WorkspaceSession::class, 'plan_sessions');
    }

    public function isBookableOn(string $date): bool
    {
        if ($this->restricted_weekday === null) {
            return true;
        }

        return \Carbon\Carbon::parse($date)->dayOfWeek === $this->restricted_weekday;
    }

    public function restrictedDayName(): ?string
    {
        if ($this->restricted_weekday === null) {
            return null;
        }

        return \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($this->restricted_weekday)->format('l');
    }
}
