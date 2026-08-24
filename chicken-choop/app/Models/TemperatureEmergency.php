<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemperatureEmergency extends Model
{
    protected $fillable = [
        'temperature',
        'condition_type',
        'threshold_breached',
        'active_actuators',
        'started_at',
        'resolved_at',
        'formatted_summary',
    ];

    protected $casts = [
        'temperature' => 'float',
        'threshold_breached' => 'float',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
