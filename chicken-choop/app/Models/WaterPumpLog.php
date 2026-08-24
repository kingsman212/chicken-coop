<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterPumpLog extends Model
{
    protected $fillable = [
        'water_level',
        'water_status',
        'pump_status',
        'state_desc',
        'recorded_at',
    ];

    protected $casts = [
        'water_level' => 'float',
        'recorded_at' => 'datetime',
    ];
}
