<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemperatureLog extends Model
{
    protected $fillable = [
        'temperature',
        'status',
        'lamp_status',
        'fan_status',
        'recorded_at',
    ];

    protected $casts = [
        'temperature' => 'float',
        'recorded_at' => 'datetime',
    ];
}
