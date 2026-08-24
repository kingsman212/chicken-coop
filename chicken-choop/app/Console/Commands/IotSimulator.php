<?php

namespace App\Console\Commands;

use App\Services\MqttService;
use Illuminate\Console\Command;

class IotSimulator extends Command
{
    protected $signature = 'iot:simulator {--interval=3 : Interval between telemetry ticks in seconds}';
    protected $description = 'Simulate IoT hardware sensors (temperature, water level, actuators) for Chicken Coop';

    public function handle(MqttService $mqttService)
    {
        $this->info("🐓 Starting IoT Chicken Coop Simulator...");
        $this->info("Broadcasting & Processing Telemetry every " . $this->option('interval') . " seconds...");

        $temp = 29.5;
        $water = 85.0;
        $trend = 0.3;

        while (true) {
            // Fluctuate temperature
            $temp += (rand(-10, 12) / 10.0);
            if ($temp > 35.5) $temp = 34.5;
            if ($temp < 18.0) $temp = 19.5;

            // Slowly decrease water level
            $water -= (rand(1, 4) / 10.0);
            if ($water < 5.0) $water = 98.0; // refill when empty

            // Handle telemetry via MqttService (updates DB, evaluates rules & emergencies)
            $tempResult = $mqttService->handleTemperatureTelemetry(round($temp, 2));
            $waterResult = $mqttService->handleWaterTelemetry(round($water, 2));

            $this->line(sprintf(
                "[%s] 🌡️ Temp: %0.1f°C (%s) | 💡 Lamp: %s | 🌀 Fan: %s || 💧 Water: %0.1f%% | 🚰 Pump: %s",
                now()->format('H:i:s'),
                $tempResult['temperature'],
                $tempResult['status'],
                $tempResult['lamp_status'],
                $tempResult['fan_status'],
                $waterResult['water_level'],
                $waterResult['pump_status']
            ));

            sleep((int)$this->option('interval'));
        }
    }
}
