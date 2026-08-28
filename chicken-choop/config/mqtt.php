<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MQTT Broker Configuration
    |--------------------------------------------------------------------------
    |
    | Pengaturan koneksi ke Eclipse Mosquitto Broker.
    | Menggunakan helper config() agar nilai tetap terbaca saat
    | `php artisan config:cache` diaktifkan di production.
    |
    */

    'host' => env('MQTT_HOST', 'mosquitto'),
    'port' => (int) env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME', 'laravel_worker'),
    'password' => env('MQTT_PASSWORD', 'rezatugasakhir09'),
];
