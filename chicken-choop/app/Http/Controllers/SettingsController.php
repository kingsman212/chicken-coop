<?php

namespace App\Http\Controllers;

use App\Models\FeedingLog;
use App\Models\SystemSetting;
use App\Models\TemperatureEmergency;
use App\Models\TemperatureLog;
use App\Models\WaterPumpLog;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::instance();

        $tableCounts = [
            'temp'      => TemperatureLog::count(),
            'water'     => WaterPumpLog::count(),
            'feeding'   => FeedingLog::count(),
            'emergency' => TemperatureEmergency::count(),
        ];

        return view('pages.settings', compact('settings', 'tableCounts'));
    }
}
