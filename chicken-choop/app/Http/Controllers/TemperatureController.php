<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\TemperatureEmergency;
use App\Models\TemperatureLog;
use Illuminate\Http\Request;

class TemperatureController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::instance();
        $latestTemp = TemperatureLog::latest('recorded_at')->first() ?? new TemperatureLog([
            'temperature' => 29.50,
            'status' => 'Normal',
            'lamp_status' => 'OFF',
            'fan_status' => 'OFF',
            'recorded_at' => now(),
        ]);

        $tempLogs = TemperatureLog::latest('recorded_at')->take(30)->get()->reverse()->values();
        $emergencies = TemperatureEmergency::latest()->take(10)->get();

        return view('pages.temperature', compact('settings', 'latestTemp', 'tempLogs', 'emergencies'));
    }
}
