<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\MqttService;
use Illuminate\Http\Request;

class ActuatorController extends Controller
{
    protected MqttService $mqttService;

    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

    public function controlActuator(Request $request)
    {
        $request->validate([
            'device' => 'required|in:lamp,fan,pump,mode',
            'action' => 'required|string', // 'ON', 'OFF', 'AUTO', 'manual', 'auto'
        ]);

        $settings = SystemSetting::instance();
        $device = $request->input('device');
        $action = strtoupper($request->input('action'));

        if ($device === 'mode') {
            $newMode = strtolower($action);
            $settings->update(['control_mode' => $newMode]);
            $this->mqttService->publish('chickencoop/config/settings', [
                'control_mode' => $newMode,
                'timestamp' => now()->toIso8601String()
            ]);
            return response()->json(['success' => true, 'message' => "Mode kontrol diubah ke {$newMode}", 'settings' => $settings]);
        }

        if ($device === 'lamp') {
            $settings->update(['lamp_manual_override' => $action]);
            $topic = 'chickencoop/device/lamp';
        } elseif ($device === 'fan') {
            $settings->update(['fan_manual_override' => $action]);
            $topic = 'chickencoop/device/fan';
        } elseif ($device === 'pump') {
            $settings->update(['pump_manual_override' => $action]);
            $topic = 'chickencoop/device/pump';
        }

        // Publish to MQTT command
        $this->mqttService->publish('chickencoop/device/control', [
            'device' => $device,
            'action' => $action,
            'mode' => $settings->control_mode,
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => "Perintah manual {$device}: {$action} berhasil dikirim",
            'settings' => $settings->fresh()
        ]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'temp_min' => 'required|numeric|min:10|max:45|lt:temp_max',
            'temp_max' => 'required|numeric|gt:temp_min|max:50',
            'water_min' => 'required|numeric|min:5|max:90',
            'control_mode' => 'required|in:auto,manual',
        ]);

        $settings = SystemSetting::instance();
        $settings->update([
            'temp_min' => $request->input('temp_min'),
            'temp_max' => $request->input('temp_max'),
            'water_min' => $request->input('water_min'),
            'control_mode' => $request->input('control_mode'),
        ]);

        // Publish settings update to MQTT
        $this->mqttService->publish('chickencoop/config/settings', [
            'temp_min' => (float)$settings->temp_min,
            'temp_max' => (float)$settings->temp_max,
            'water_min' => (float)$settings->water_min,
            'control_mode' => $settings->control_mode,
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan sistem berhasil diperbarui',
            'settings' => $settings
        ]);
    }
}
