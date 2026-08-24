<?php

namespace App\Console\Commands;

use App\Services\MqttService;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListenCommand extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen to MQTT topics from IoT sensors and update database';

    public function handle(MqttService $mqttService)
    {
        $host = env('MQTT_HOST', '127.0.0.1');
        $port = (int) env('MQTT_PORT', 1883);
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');
        $clientId = 'laravel_sub_' . uniqid();

        $this->info("Connecting to MQTT Broker {$host}:{$port}...");

        try {
            $mqtt = new MqttClient($host, $port, $clientId);
            $settings = (new ConnectionSettings())->setConnectTimeout(10);
            if (!empty($username) && !empty($password)) {
                $settings->setAuth($username, $password);
            }
            $mqtt->connect($settings, true);

            $this->info("Subscribed to chickencoop/#. Listening for telemetry...");

            $mqtt->subscribe('chickencoop/sensor/temperature', function (string $topic, string $message) use ($mqttService) {
                $payload = json_decode($message, true);
                if (isset($payload['temperature'])) {
                    $mqttService->handleTemperatureTelemetry(
                        (float)$payload['temperature'],
                        $payload['lamp_status'] ?? null,
                        $payload['fan_status'] ?? null
                    );
                }
            }, 0);

            $mqtt->subscribe('chickencoop/sensor/water', function (string $topic, string $message) use ($mqttService) {
                $payload = json_decode($message, true);
                if (isset($payload['water_level'])) {
                    $mqttService->handleWaterTelemetry(
                        (float)$payload['water_level'],
                        $payload['pump_status'] ?? null
                    );
                }
            }, 0);

            $mqtt->subscribe('chickencoop/sensor/feed_done', function (string $topic, string $message) use ($mqttService) {
                $payload = json_decode($message, true);
                $mqttService->recordFeeding(
                    $payload['source'] ?? 'Terjadwal (RTC Alat)',
                    $payload['schedule_id'] ?? null,
                    $payload['label'] ?? 'Jadwal Pakan Otomatis (RTC)',
                    (int)($payload['portion_grams'] ?? 500)
                );
            }, 0);

            // Ketika ESP8266 baru connect atau request sync jadwal & RTC
            $mqtt->subscribe('chickencoop/feed/request_sync', function (string $topic, string $message) use ($mqttService) {
                $this->info("ESP8266 meminta sinkronisasi jadwal & RTC. Mengirim data...");
                $mqttService->syncAllSchedules();
                $mqttService->syncRtcTime();
            }, 0);

            $mqtt->subscribe('chickencoop/device/ready', function (string $topic, string $message) use ($mqttService) {
                $this->info("Device terhubung. Mengirim sinkronisasi jadwal...");
                $mqttService->syncAllSchedules();
            }, 0);

            $mqtt->loop(true);
        } catch (\Exception $e) {
            $this->error("MQTT Listener error: " . $e->getMessage());
        }
    }
}
