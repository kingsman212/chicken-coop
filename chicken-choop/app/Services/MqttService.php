<?php

namespace App\Services;

use App\Models\FeedingLog;
use App\Models\SystemSetting;
use App\Models\TemperatureEmergency;
use App\Models\TemperatureLog;
use App\Models\WaterPumpLog;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MqttService
{
    protected string $host;
    protected int $port;
    protected string $clientId;
    protected ?string $username;
    protected ?string $password;

    public function __construct()
    {
        $this->host = config('mqtt.host', 'mosquitto');
        $this->port = (int) config('mqtt.port', 1883);
        $this->clientId = 'laravel_chicken_coop_' . uniqid();
        $this->username = config('mqtt.username');
        $this->password = config('mqtt.password');
    }

    /**
     * Publish a message to MQTT topic
     */
    public function publish(string $topic, array $payload): bool
    {
        try {
            $mqtt = new MqttClient($this->host, $this->port, $this->clientId);
            $connectionSettings = (new ConnectionSettings())
                ->setConnectTimeout(5)
                ->setUseTls(false);

            if (!empty($this->username)) {
                $connectionSettings = $connectionSettings->setUsername($this->username);
            }
            if (!empty($this->password)) {
                $connectionSettings = $connectionSettings->setPassword($this->password);
            }

            $mqtt->connect($connectionSettings, true);
            $mqtt->publish($topic, json_encode($payload), 0);
            $mqtt->disconnect();
            return true;
        } catch (\Exception $e) {
            Log::error("MQTT Publish Error on {$topic}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process incoming Temperature Telemetry
     *
     * Optimasi: Data hanya disimpan ke database ketika terjadi perubahan
     * status yang penting (status suhu, lampu, atau kipas berubah) ATAU
     * setiap TEMP_SNAPSHOT_INTERVAL menit sebagai snapshot periodik.
     * Data real-time tetap dikembalikan untuk dashboard tanpa gangguan.
     */
    public function handleTemperatureTelemetry(float $temperature, ?string $lampStatus = null, ?string $fanStatus = null): array
    {
        $settings = SystemSetting::instance();

        $calculatedLamp = 'OFF';
        $calculatedFan = 'OFF';
        $tempStatus = 'Normal';

        // Auto logic evaluation (untuk keperluan log & emergency saja)
        if ($temperature > $settings->temp_max) {
            $calculatedFan = 'ON';
            $calculatedLamp = 'OFF';
            $tempStatus = ($temperature >= $settings->temp_emergency_high) ? 'Emergency' : 'Terlalu panas';
        } elseif ($temperature < $settings->temp_min) {
            $calculatedLamp = 'ON';
            $calculatedFan = 'OFF';
            $tempStatus = ($temperature <= $settings->temp_emergency_low) ? 'Emergency' : 'Terlalu dingin';
        } else {
            $calculatedLamp = 'OFF';
            $calculatedFan = 'OFF';
            $tempStatus = 'Normal';
        }

        // Apply Manual Override jika ada — untuk keperluan tampilan di web
        if ($settings->lamp_manual_override !== 'AUTO') {
            $calculatedLamp = $settings->lamp_manual_override;
        }
        if ($settings->fan_manual_override !== 'AUTO') {
            $calculatedFan = $settings->fan_manual_override;
        }

        // Gunakan status yang dilaporkan firmware sebagai sumber kebenaran (Opsi A)
        // Firmware adalah master kontrol; server hanya menerima dan mencatat.
        $lampFinal = $lampStatus ?? $calculatedLamp;
        $fanFinal  = $fanStatus  ?? $calculatedFan;

        // ── [IMPROVEMENT] Simpan data realtime ke Cache (TTL 30 detik) ────────
        // Ini memastikan /api/state selalu mengembalikan data terbaru
        // bahkan saat tidak ada perubahan status (DB tidak selalu diupdate).
        \Illuminate\Support\Facades\Cache::put('last_temperature', [
            'temperature' => $temperature,
            'status'      => $tempStatus,
            'lamp_status' => $lampFinal,
            'fan_status'  => $fanFinal,
            'recorded_at' => now()->toIso8601String(),
        ], 30);
        // ─────────────────────────────────────────────────────────────────────

        // ── Event-Based DB Write ───────────────────────────────────────────
        $lastLog = TemperatureLog::latest('recorded_at')->first();

        $shouldSave = false;
        $saveReason = '';

        if (!$lastLog) {
            $shouldSave = true;
            $saveReason = 'initial';
        } elseif ($tempStatus !== $lastLog->status) {
            $shouldSave = true;
            $saveReason = "status_change: {$lastLog->status} → {$tempStatus}";
        } elseif ($lampFinal !== $lastLog->lamp_status) {
            $shouldSave = true;
            $saveReason = "lamp_change: {$lastLog->lamp_status} → {$lampFinal}";
        } elseif ($fanFinal !== $lastLog->fan_status) {
            $shouldSave = true;
            $saveReason = "fan_change: {$lastLog->fan_status} → {$fanFinal}";
        } elseif ($lastLog->recorded_at->diffInMinutes(now()) >= 30) {
            $shouldSave = true;
            $saveReason = 'periodic_snapshot_30min';
        }

        $log = null;
        if ($shouldSave) {
            $log = TemperatureLog::create([
                'temperature' => $temperature,
                'status'      => $tempStatus,
                'lamp_status' => $lampFinal,
                'fan_status'  => $fanFinal,
                'recorded_at' => now(),
            ]);
            Log::info("[TempLog] Disimpan ke DB — Alasan: {$saveReason} | Suhu: {$temperature}°C | Status: {$tempStatus} | Lampu: {$lampFinal} | Kipas: {$fanFinal}");
        }
        // ──────────────────────────────────────────────────────────────────

        // Emergency Monitoring logic (selalu dievaluasi setiap telemetri)
        $this->evaluateEmergency($temperature, $settings, $lampFinal, $fanFinal);

        // ── [FIX #1] HAPUS publish relay ke firmware dari sini ─────────────
        // Sebelumnya: server publish device/lamp & device/fan setiap telemetri
        // datang → menyebabkan mode manual di firmware terus di-override.
        //
        // Dengan Opsi A (firmware sebagai master kontrol):
        //   - Server HANYA publish ke relay saat user EKSPLISIT klik tombol
        //     (sudah ditangani di ActuatorController)
        //   - Server TIDAK boleh publish relay secara otomatis saat telemetri masuk
        // ─────────────────────────────────────────────────────────────────────

        return [
            'temperature' => $temperature,
            'status'      => $tempStatus,
            'lamp_status' => $lampFinal,
            'fan_status'  => $fanFinal,
        ];
    }

    /**
     * Evaluate and update emergency records
     */
    protected function evaluateEmergency(float $temp, SystemSetting $settings, string $lampStatus, string $fanStatus): void
    {
        $activeEmergency = TemperatureEmergency::whereNull('resolved_at')->latest()->first();

        if ($temp >= $settings->temp_emergency_high) {
            $conditionType = 'Emergency Suhu Tinggi';
            $activeActuator = "Kipas {$fanStatus}";
            $threshold = $settings->temp_emergency_high;
            $nowStr = now()->format('d-m-Y H:i');
            $summary = "{$nowStr} — Suhu {$temp}°C — {$conditionType} — {$activeActuator}";

            if (!$activeEmergency || $activeEmergency->condition_type !== $conditionType) {
                TemperatureEmergency::create([
                    'temperature' => $temp,
                    'condition_type' => $conditionType,
                    'threshold_breached' => $threshold,
                    'active_actuators' => $activeActuator,
                    'started_at' => now(),
                    'formatted_summary' => $summary,
                ]);
            }
        } elseif ($temp <= $settings->temp_emergency_low) {
            $conditionType = 'Emergency Suhu Rendah';
            $activeActuator = "Lampu {$lampStatus}";
            $threshold = $settings->temp_emergency_low;
            $nowStr = now()->format('d-m-Y H:i');
            $summary = "{$nowStr} — Suhu {$temp}°C — {$conditionType} — {$activeActuator}";

            if (!$activeEmergency || $activeEmergency->condition_type !== $conditionType) {
                TemperatureEmergency::create([
                    'temperature' => $temp,
                    'condition_type' => $conditionType,
                    'threshold_breached' => $threshold,
                    'active_actuators' => $activeActuator,
                    'started_at' => now(),
                    'formatted_summary' => $summary,
                ]);
            }
        } else {
            // Normal range: resolve active emergency if any
            if ($activeEmergency && $temp >= $settings->temp_min && $temp <= $settings->temp_max) {
                $activeEmergency->update([
                    'resolved_at' => now()
                ]);
            }
        }
    }

    /**
     * Process incoming Water Level Telemetry
     *
     * Optimasi: Data hanya disimpan ke database ketika terjadi perubahan
     * status yang penting (water_status atau pump_status berubah) ATAU
     * setiap 30 menit sebagai snapshot periodik.
     * Publish MQTT ke firmware tetap selalu dilakukan untuk kontrol real-time.
     */
    public function handleWaterTelemetry(float $waterLevel, ?string $pumpStatus = null): array
    {
        $settings = SystemSetting::instance();

        $waterStatus = 'Normal';
        if ($waterLevel <= 10.0) {
            $waterStatus = 'Hampir habis';
        } elseif ($waterLevel <= $settings->water_min) {
            $waterStatus = 'Rendah';
        } else {
            $waterStatus = 'Normal';
        }

        $calculatedPump = 'OFF';
        if ($waterLevel <= $settings->water_min) {
            $calculatedPump = 'ON';
        } elseif ($waterLevel >= 95.0) {
            $calculatedPump = 'OFF';
        }

        // Apply Manual Override for Pump
        if ($settings->pump_manual_override !== 'AUTO') {
            $calculatedPump = $settings->pump_manual_override;
        }

        $pumpFinal = $pumpStatus ?? $calculatedPump;

        if ($pumpFinal === 'ON') {
            $waterStatus = 'Pengisian';
            $stateDesc = 'Pompa Air: ON – Sedang Mengisi';
        } else {
            $stateDesc = ($waterStatus === 'Normal') ? 'Pompa Air: OFF – Air Normal' : "Pompa Air: OFF – Air {$waterStatus}";
        }

        // ── [IMPROVEMENT] Simpan data realtime ke Cache (TTL 30 detik) ────────
        \Illuminate\Support\Facades\Cache::put('last_water', [
            'water_level'  => $waterLevel,
            'water_status' => $waterStatus,
            'pump_status'  => $pumpFinal,
            'state_desc'   => $stateDesc,
            'recorded_at'  => now()->toIso8601String(),
        ], 30);
        // ─────────────────────────────────────────────────────────────────────

        // ── Event-Based DB Write ───────────────────────────────────────────
        $lastLog = WaterPumpLog::latest('recorded_at')->first();

        $shouldSave = false;
        $saveReason = '';

        if (!$lastLog) {
            // Belum ada data sama sekali → simpan pertama kali
            $shouldSave = true;
            $saveReason = 'initial';
        } elseif ($waterStatus !== $lastLog->water_status) {
            // Status air berubah (Normal ↔ Rendah ↔ Hampir habis ↔ Pengisian)
            $shouldSave = true;
            $saveReason = "water_status_change: {$lastLog->water_status} → {$waterStatus}";
        } elseif ($pumpFinal !== $lastLog->pump_status) {
            // Status pompa berubah (OFF → ON atau ON → OFF)
            $shouldSave = true;
            $saveReason = "pump_change: {$lastLog->pump_status} → {$pumpFinal}";
        } elseif ($lastLog->recorded_at->diffInMinutes(now()) >= 30) {
            // Snapshot periodik setiap 30 menit (untuk grafik historis)
            $shouldSave = true;
            $saveReason = 'periodic_snapshot_30min';
        }

        $log = null;
        if ($shouldSave) {
            $log = WaterPumpLog::create([
                'water_level'  => $waterLevel,
                'water_status' => $waterStatus,
                'pump_status'  => $pumpFinal,
                'state_desc'   => $stateDesc,
                'recorded_at'  => now(),
            ]);
            Log::info("[WaterLog] Disimpan ke DB — Alasan: {$saveReason} | Level: {$waterLevel}% | Status: {$waterStatus} | Pompa: {$pumpFinal}");
        }
        // ──────────────────────────────────────────────────────────────────

        // Publish ke firmware (selalu, untuk kontrol pompa real-time)
        $this->publish('chickencoop/device/pump', [
            'status'     => $pumpFinal,
            'state_desc' => $stateDesc,
            'water_level'=> $waterLevel,
            'timestamp'  => now()->toIso8601String()
        ]);

        return [
            'water_level'  => $waterLevel,
            'water_status' => $waterStatus,
            'pump_status'  => $pumpFinal,
            'state_desc'   => $stateDesc,
        ];
    }


    /**
     * Process Feeding Action
     */
    public function recordFeeding(string $source = 'Manual', ?int $scheduleId = null, ?string $scheduleLabel = null, int $portionGrams = 500): FeedingLog
    {
        $log = FeedingLog::create([
            'feeding_schedule_id' => $scheduleId,
            'schedule_label' => $scheduleLabel ?? ($source === 'Manual' ? 'Pemberian Pakan Manual' : 'Terjadwal'),
            'source' => $source,
            'status' => 'Selesai',
            'portion_grams' => $portionGrams,
            'fed_at' => now(),
        ]);

        $this->publish('chickencoop/feed/status', [
            'status' => 'Selesai',
            'source' => $source,
            'portion_grams' => $portionGrams,
            'fed_at' => $log->fed_at->toIso8601String()
        ]);

        return $log;
    }

    /**
     * Sinkronkan seluruh daftar jadwal pakan aktif ke Firmware ESP8266 via MQTT
     */
    public function syncAllSchedules(): bool
    {
        $schedules = \App\Models\FeedingSchedule::where('is_active', true)->orderBy('time')->get();

        $scheduleList = [];
        foreach ($schedules as $s) {
            $parsed = Carbon::parse($s->time);
            $scheduleList[] = [
                'id'            => $s->id,
                'label'         => $s->label,
                'hour'          => (int) $parsed->format('H'),
                'minute'        => (int) $parsed->format('i'),
                'portion_grams' => (int) $s->portion_grams,
                'is_active'     => (bool) $s->is_active,
            ];
        }

        $now = now();
        $payload = [
            'action'      => 'SYNC_ALL',
            'total'       => count($scheduleList),
            'server_time' => [
                'year'   => (int) $now->format('Y'),
                'month'  => (int) $now->format('m'),
                'day'    => (int) $now->format('d'),
                'hour'   => (int) $now->format('H'),
                'minute' => (int) $now->format('i'),
                'second' => (int) $now->format('s'),
                'epoch'  => $now->timestamp,
            ],
            'schedules'   => $scheduleList,
            'timestamp'   => $now->toIso8601String(),
        ];

        return $this->publish('chickencoop/feed/schedules/sync', $payload);
    }

    /**
     * Sinkronkan waktu RTC DS3231 pada ESP8266 dengan waktu server
     */
    public function syncRtcTime(): bool
    {
        $now = now();
        $payload = [
            'year'      => (int) $now->format('Y'),
            'month'     => (int) $now->format('m'),
            'day'       => (int) $now->format('d'),
            'hour'      => (int) $now->format('H'),
            'minute'    => (int) $now->format('i'),
            'second'    => (int) $now->format('s'),
            'epoch'     => $now->timestamp,
            'formatted' => $now->format('Y-m-d H:i:s'),
            'timestamp' => $now->toIso8601String(),
        ];

        return $this->publish('chickencoop/rtc/sync', $payload);
    }
}
