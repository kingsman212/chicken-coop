<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('temp_min', 4, 2)->default(26.00);
            $table->decimal('temp_max', 4, 2)->default(32.00);
            $table->decimal('temp_emergency_high', 4, 2)->default(34.00);
            $table->decimal('temp_emergency_low', 4, 2)->default(20.00);
            $table->decimal('water_min', 5, 2)->default(20.00);
            $table->string('control_mode')->default('auto'); // 'auto' or 'manual'
            $table->string('lamp_manual_override')->default('AUTO'); // 'AUTO', 'ON', 'OFF'
            $table->string('fan_manual_override')->default('AUTO'); // 'AUTO', 'ON', 'OFF'
            $table->string('pump_manual_override')->default('AUTO'); // 'AUTO', 'ON', 'OFF'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
