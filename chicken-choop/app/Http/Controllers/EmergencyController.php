<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\TemperatureEmergency;
use Illuminate\Http\Request;

class EmergencyController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::instance();
        $emergencies = TemperatureEmergency::latest('started_at')->paginate(20);
        $activeEmergency = TemperatureEmergency::whereNull('resolved_at')->latest()->first();

        return view('pages.emergency', compact('settings', 'emergencies', 'activeEmergency'));
    }

    public function resolve($id)
    {
        $emergency = TemperatureEmergency::findOrFail($id);
        $emergency->update([
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status emergency berhasil diselesaikan',
            'emergency' => $emergency
        ]);
    }
}
