<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\WaterPumpLog;
use Illuminate\Http\Request;

class WaterController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::instance();
        $latestWater = WaterPumpLog::latest('recorded_at')->first() ?? new WaterPumpLog([
            'water_level' => 85.00,
            'water_status' => 'Normal',
            'pump_status' => 'OFF',
            'state_desc' => 'Pompa Air: OFF – Air Normal',
            'recorded_at' => now(),
        ]);

        $waterLogs = WaterPumpLog::latest('recorded_at')->take(20)->get();

        return view('pages.water', compact('settings', 'latestWater', 'waterLogs'));
    }
}
