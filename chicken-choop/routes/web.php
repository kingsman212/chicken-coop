<?php

use App\Http\Controllers\ActuatorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\FeedingController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\TemperatureController;
use App\Http\Controllers\WaterController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Web Page Routes (Protected by Auth)
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('overview');
    Route::get('/temperature', [TemperatureController::class, 'index'])->name('temperature');
    Route::get('/water', [WaterController::class, 'index'])->name('water');
    Route::get('/feeding', [FeedingController::class, 'index'])->name('feeding');
    Route::get('/emergency', [EmergencyController::class, 'index'])->name('emergency');
    Route::get('/logs', [LogsController::class, 'index'])->name('logs');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
});

// REST & State API Endpoints
Route::prefix('api')->group(function () {
    Route::get('/state', [DashboardController::class, 'getState']);
    Route::get('/chart-data', [TelemetryController::class, 'getChartData']);
    Route::get('/logs', [TelemetryController::class, 'getLogs']);

    // Telemetry Ingestion
    Route::post('/telemetry/temperature', [TelemetryController::class, 'receiveTemperature']);
    Route::post('/telemetry/water', [TelemetryController::class, 'receiveWater']);

    // Actuators & Settings
    Route::post('/actuator/control', [ActuatorController::class, 'controlActuator']);
    Route::post('/settings/update', [ActuatorController::class, 'updateSettings']);

    // Feeding Management & RTC Sync
    Route::post('/feed/manual', [FeedingController::class, 'manualFeed']);
    Route::post('/schedules', [FeedingController::class, 'storeSchedule']);
    Route::post('/schedules/sync', [FeedingController::class, 'syncSchedules']);
    Route::put('/schedules/{id}', [FeedingController::class, 'updateSchedule']);
    Route::delete('/schedules/{id}', [FeedingController::class, 'destroySchedule']);
    Route::post('/feed/schedule', [FeedingController::class, 'storeSchedule']);
    Route::post('/rtc/sync', [FeedingController::class, 'syncRtc']);

    // Database Maintenance (Truncate Logs)
    Route::post('/database/truncate', [LogsController::class, 'truncateTable']);

    // Emergency Management
    Route::post('/emergency/{id}/resolve', [EmergencyController::class, 'resolve']);
});
