<?php

namespace App\Http\Controllers;

use App\Models\FeedingLog;
use App\Models\FeedingSchedule;
use App\Models\SystemSetting;
use App\Models\TemperatureEmergency;
use App\Models\TemperatureLog;
use App\Models\WaterPumpLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
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

        $latestWater = WaterPumpLog::latest('recorded_at')->first() ?? new WaterPumpLog([
            'water_level' => 85.00,
            'water_status' => 'Normal',
            'pump_status' => 'OFF',
            'state_desc' => 'Pompa Air: OFF – Air Normal',
            'recorded_at' => now(),
        ]);

        $lastFeeding = FeedingLog::latest('fed_at')->first();

        // Calculate next feeding schedule
        $schedules = FeedingSchedule::where('is_active', true)->get();
        $nextSchedule = null;
        $nowTime = now()->format('H:i:s');

        $upcoming = $schedules->filter(fn($s) => $s->time >= $nowTime)->sortBy('time')->first();
        if ($upcoming) {
            $nextSchedule = [
                'label' => $upcoming->label,
                'time' => Carbon::parse($upcoming->time)->format('H:i'),
                'portion_grams' => $upcoming->portion_grams,
                'is_today' => true,
            ];
        } else {
            $firstTomorrow = $schedules->sortBy('time')->first();
            if ($firstTomorrow) {
                $nextSchedule = [
                    'label' => $firstTomorrow->label,
                    'time' => Carbon::parse($firstTomorrow->time)->format('H:i'),
                    'portion_grams' => $firstTomorrow->portion_grams,
                    'is_today' => false,
                ];
            }
        }

        $emergencies = TemperatureEmergency::latest('started_at')->take(20)->get();
        $allSchedules = FeedingSchedule::orderBy('time')->get();
        $feedingLogs = FeedingLog::latest('fed_at')->take(15)->get();
        $tempLogs = TemperatureLog::latest('recorded_at')->take(20)->get()->reverse()->values();

        return view('pages.overview', compact(
            'settings',
            'latestTemp',
            'latestWater',
            'lastFeeding',
            'nextSchedule',
            'emergencies',
            'allSchedules',
            'feedingLogs',
            'tempLogs'
        ));
    }

    public function getState()
    {
        $settings = SystemSetting::instance();
        $latestTemp = TemperatureLog::latest('recorded_at')->first();
        $latestWater = WaterPumpLog::latest('recorded_at')->first();
        $lastFeeding = FeedingLog::latest('fed_at')->first();
        $activeEmergency = TemperatureEmergency::whereNull('resolved_at')->latest('started_at')->first();

        // Next schedule
        $schedules = FeedingSchedule::where('is_active', true)->get();
        $nextSchedule = null;
        $nowTime = now()->format('H:i:s');
        $upcoming = $schedules->filter(fn($s) => $s->time >= $nowTime)->sortBy('time')->first();
        if ($upcoming) {
            $nextSchedule = [
                'label' => $upcoming->label,
                'time' => Carbon::parse($upcoming->time)->format('H:i'),
                'portion_grams' => $upcoming->portion_grams,
                'formatted' => 'Hari Ini ' . Carbon::parse($upcoming->time)->format('H:i'),
            ];
        } else {
            $firstTomorrow = $schedules->sortBy('time')->first();
            if ($firstTomorrow) {
                $nextSchedule = [
                    'label' => $firstTomorrow->label,
                    'time' => Carbon::parse($firstTomorrow->time)->format('H:i'),
                    'portion_grams' => $firstTomorrow->portion_grams,
                    'formatted' => 'Besok ' . Carbon::parse($firstTomorrow->time)->format('H:i'),
                ];
            }
        }

        return response()->json([
            'settings' => $settings,
            'latest_temp' => $latestTemp,
            'latest_water' => $latestWater,
            'last_feeding' => $lastFeeding ? [
                'source' => $lastFeeding->source,
                'schedule_label' => $lastFeeding->schedule_label,
                'portion_grams' => $lastFeeding->portion_grams,
                'formatted_time' => $lastFeeding->fed_at->format('d-m-Y H:i'),
                'diff_for_humans' => $lastFeeding->fed_at->diffForHumans(),
            ] : null,
            'next_schedule' => $nextSchedule,
            'active_emergency' => $activeEmergency,
        ]);
    }
}
