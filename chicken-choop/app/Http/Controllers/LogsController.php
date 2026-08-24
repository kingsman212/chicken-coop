<?php

namespace App\Http\Controllers;

use App\Models\FeedingLog;
use App\Models\TemperatureEmergency;
use App\Models\TemperatureLog;
use App\Models\WaterPumpLog;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'temp');

        $tempLogs = TemperatureLog::latest('recorded_at')->take(30)->get();
        $waterLogs = WaterPumpLog::latest('recorded_at')->take(30)->get();
        $feedingLogs = FeedingLog::latest('fed_at')->take(30)->get();
        $emergencies = TemperatureEmergency::latest('started_at')->take(30)->get();

        $tableCounts = [
            'temp'      => TemperatureLog::count(),
            'water'     => WaterPumpLog::count(),
            'feeding'   => FeedingLog::count(),
            'emergency' => TemperatureEmergency::count(),
        ];

        return view('pages.logs', compact('tab', 'tempLogs', 'waterLogs', 'feedingLogs', 'emergencies', 'tableCounts'));
    }

    /**
     * Truncate data pada tabel log database MySQL
     */
    public function truncateTable(Request $request)
    {
        $request->validate([
            'table' => 'required|string|in:temp,water,feeding,emergency,all'
        ]);

        $table = $request->input('table');
        $message = '';

        switch ($table) {
            case 'temp':
                TemperatureLog::truncate();
                $message = 'Tabel log suhu (temperature_logs) berhasil dikosongkan.';
                break;

            case 'water':
                WaterPumpLog::truncate();
                $message = 'Tabel log level air & pompa (water_pump_logs) berhasil dikosongkan.';
                break;

            case 'feeding':
                FeedingLog::truncate();
                $message = 'Tabel riwayat pakan (feeding_logs) berhasil dikosongkan.';
                break;

            case 'emergency':
                TemperatureEmergency::truncate();
                $message = 'Tabel riwayat emergency suhu (temperature_emergencies) berhasil dikosongkan.';
                break;

            case 'all':
                TemperatureLog::truncate();
                WaterPumpLog::truncate();
                FeedingLog::truncate();
                TemperatureEmergency::truncate();
                $message = 'Semua tabel log database MySQL berhasil dikosongkan.';
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'counts' => [
                'temp'      => TemperatureLog::count(),
                'water'     => WaterPumpLog::count(),
                'feeding'   => FeedingLog::count(),
                'emergency' => TemperatureEmergency::count(),
            ]
        ]);
    }
}
