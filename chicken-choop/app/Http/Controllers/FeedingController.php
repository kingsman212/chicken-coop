<?php

namespace App\Http\Controllers;

use App\Models\FeedingLog;
use App\Models\FeedingSchedule;
use App\Services\MqttService;
use Illuminate\Http\Request;

class FeedingController extends Controller
{
    protected MqttService $mqttService;

    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

    public function index()
    {
        $allSchedules = FeedingSchedule::orderBy('time')->get();
        $feedingLogs = FeedingLog::latest('fed_at')->take(20)->get();
        $lastFeeding = FeedingLog::latest('fed_at')->first();

        // Calculate next schedule
        $schedules = FeedingSchedule::where('is_active', true)->get();
        $nextSchedule = null;
        $nowTime = now()->format('H:i:s');
        $upcoming = $schedules->filter(fn($s) => $s->time >= $nowTime)->sortBy('time')->first();
        if ($upcoming) {
            $nextSchedule = [
                'label' => $upcoming->label,
                'time' => \Carbon\Carbon::parse($upcoming->time)->format('H:i'),
                'portion_grams' => $upcoming->portion_grams,
                'is_today' => true,
            ];
        } else {
            $firstTomorrow = $schedules->sortBy('time')->first();
            if ($firstTomorrow) {
                $nextSchedule = [
                    'label' => $firstTomorrow->label,
                    'time' => \Carbon\Carbon::parse($firstTomorrow->time)->format('H:i'),
                    'portion_grams' => $firstTomorrow->portion_grams,
                    'is_today' => false,
                ];
            }
        }

        return view('pages.feeding', compact('allSchedules', 'feedingLogs', 'lastFeeding', 'nextSchedule'));
    }

    public function manualFeed(Request $request)
    {
        $portion = (int) $request->input('portion_grams', 500);

        // Kirim perintah ke firmware via MQTT agar servo bergerak
        $this->mqttService->publish('chickencoop/device/control', [
            'device'        => 'feed',
            'action'        => 'FEED',
            'portion_grams' => $portion,
            'timestamp'     => now()->toIso8601String(),
        ]);

        // Catat log ke database (akan ditimpa/dikonfirmasi oleh feed_done dari firmware)
        $log = $this->mqttService->recordFeeding(
            source: 'Manual',
            scheduleId: null,
            scheduleLabel: 'Pemberian Pakan Manual',
            portionGrams: $portion
        );

        return response()->json([
            'success' => true,
            'message' => 'Perintah pakan manual berhasil dikirim ke alat',
            'log' => [
                'id'             => $log->id,
                'source'         => $log->source,
                'schedule_label' => $log->schedule_label,
                'status'         => $log->status,
                'portion_grams'  => $log->portion_grams,
                'formatted_time' => $log->fed_at->format('d-m-Y H:i:s'),
                'diff_for_humans'=> $log->fed_at->diffForHumans(),
            ]
        ]);
    }

    public function storeSchedule(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:100',
            'time' => 'required',
            'portion_grams' => 'nullable|integer|min:50|max:5000',
        ]);

        $timeVal = $request->input('time');
        if (strlen($timeVal) === 5) {
            $timeVal .= ':00';
        }

        $schedule = FeedingSchedule::create([
            'label' => $request->input('label'),
            'time' => $timeVal,
            'portion_grams' => $request->input('portion_grams', 500),
            'is_active' => true,
        ]);

        // Publish schedule update via MQTT
        $this->mqttService->publish('chickencoop/feed/schedule', [
            'action' => 'CREATE',
            'id' => $schedule->id,
            'label' => $schedule->label,
            'time' => $schedule->time,
            'portion_grams' => $schedule->portion_grams,
            'timestamp' => now()->toIso8601String()
        ]);

        // Kirim ulang daftar seluruh jadwal aktif dan jam server ke ESP8266
        $this->mqttService->syncAllSchedules();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal pakan berhasil ditambahkan dan disinkronkan ke alat',
            'schedule' => $schedule
        ]);
    }

    public function updateSchedule(Request $request, $id)
    {
        $schedule = FeedingSchedule::findOrFail($id);

        $request->validate([
            'label' => 'required|string|max:100',
            'time' => 'required',
            'portion_grams' => 'nullable|integer|min:50|max:5000',
            'is_active' => 'nullable|boolean',
        ]);

        $timeVal = $request->input('time');
        if (strlen($timeVal) === 5) {
            $timeVal .= ':00';
        }

        $schedule->update([
            'label' => $request->input('label'),
            'time' => $timeVal,
            'portion_grams' => $request->input('portion_grams', $schedule->portion_grams),
            'is_active' => $request->boolean('is_active', $schedule->is_active),
        ]);

        $this->mqttService->publish('chickencoop/feed/schedule', [
            'action' => 'UPDATE',
            'id' => $schedule->id,
            'label' => $schedule->label,
            'time' => $schedule->time,
            'portion_grams' => $schedule->portion_grams,
            'is_active' => $schedule->is_active,
            'timestamp' => now()->toIso8601String()
        ]);

        // Kirim ulang daftar seluruh jadwal aktif dan jam server ke ESP8266
        $this->mqttService->syncAllSchedules();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal pakan berhasil diperbarui dan disinkronkan ke alat',
            'schedule' => $schedule
        ]);
    }

    public function destroySchedule($id)
    {
        $schedule = FeedingSchedule::findOrFail($id);
        $schedule->delete();

        $this->mqttService->publish('chickencoop/feed/schedule', [
            'action' => 'DELETE',
            'id' => $id,
            'timestamp' => now()->toIso8601String()
        ]);

        // Kirim ulang daftar seluruh jadwal aktif dan jam server ke ESP8266
        $this->mqttService->syncAllSchedules();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal pakan berhasil dihapus dan disinkronkan ke alat'
        ]);
    }

    /**
     * Sinkronkan seluruh jadwal pakan ke firmware via MQTT secara manual
     */
    public function syncSchedules()
    {
        $success = $this->mqttService->syncAllSchedules();

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Seluruh jadwal pakan berhasil dikirim ke modul alat' : 'Gagal mengirim jadwal ke broker MQTT'
        ]);
    }

    /**
     * Sinkronkan waktu RTC DS3231 pada firmware dengan waktu server
     */
    public function syncRtc()
    {
        $success = $this->mqttService->syncRtcTime();

        return response()->json([
            'success' => $success,
            'server_time' => now()->format('Y-m-d H:i:s'),
            'message' => $success ? 'Waktu jam RTC DS3231 berhasil disinkronkan dengan server' : 'Gagal mengirim perintah sinkronisasi waktu RTC'
        ]);
    }
}
