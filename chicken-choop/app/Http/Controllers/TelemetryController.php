<?php

namespace App\Http\Controllers;

use App\Models\FeedingLog;
use App\Models\TemperatureEmergency;
use App\Models\TemperatureLog;
use App\Models\WaterPumpLog;
use App\Services\MqttService;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    protected MqttService $mqttService;

    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

    public function receiveTemperature(Request $request)
    {
        $request->validate([
            'temperature' => 'required|numeric',
            'lamp_status' => 'nullable|string',
            'fan_status' => 'nullable|string',
        ]);

        $temp = (float)$request->input('temperature');
        $result = $this->mqttService->handleTemperatureTelemetry(
            $temp,
            $request->input('lamp_status'),
            $request->input('fan_status')
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function receiveWater(Request $request)
    {
        $request->validate([
            'water_level' => 'required|numeric|min:0|max:100',
            'pump_status' => 'nullable|string',
        ]);

        $water = (float)$request->input('water_level');
        $result = $this->mqttService->handleWaterTelemetry(
            $water,
            $request->input('pump_status')
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function getChartData()
    {
        $logs = TemperatureLog::latest('recorded_at')->take(30)->get()->reverse()->values();

        $labels = $logs->map(fn($log) => $log->recorded_at->format('H:i:s'));
        $temperatures = $logs->pluck('temperature');

        return response()->json([
            'labels' => $labels,
            'temperatures' => $temperatures,
        ]);
    }

    public function getLogs(Request $request)
    {
        $type = $request->query('type', 'temp');

        if ($type === 'temp') {
            $data = TemperatureLog::latest('recorded_at')->paginate(15);
        } elseif ($type === 'emergency') {
            $data = TemperatureEmergency::latest('started_at')->paginate(15);
        } elseif ($type === 'water') {
            $data = WaterPumpLog::latest('recorded_at')->paginate(15);
        } elseif ($type === 'feeding') {
            $data = FeedingLog::latest('fed_at')->paginate(15);
        } else {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        return response()->json($data);
    }
}
