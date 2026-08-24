<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'temp_min',
        'temp_max',
        'temp_emergency_high',
        'temp_emergency_low',
        'water_min',
        'control_mode',
        'lamp_manual_override',
        'fan_manual_override',
        'pump_manual_override',
    ];

    protected $casts = [
        'temp_min' => 'float',
        'temp_max' => 'float',
        'temp_emergency_high' => 'float',
        'temp_emergency_low' => 'float',
        'water_min' => 'float',
    ];

    public static function instance(): self
    {
        return static::firstOrCreate([], [
            'temp_min' => 26.00,
            'temp_max' => 32.00,
            'temp_emergency_high' => 34.00,
            'temp_emergency_low' => 20.00,
            'water_min' => 20.00,
            'control_mode' => 'auto',
            'lamp_manual_override' => 'AUTO',
            'fan_manual_override' => 'AUTO',
            'pump_manual_override' => 'AUTO',
        ]);
    }
}
