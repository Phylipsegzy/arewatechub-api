<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanDuration extends Model
{
    protected $fillable = [
        'plan_id', 'workspace_session_id', 'room_id', 'name', 'days', 'price', 'internet_price',
    ];

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
}
