<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_pump_logs', function (Blueprint $table) {
            $table->id();
            $table->decimal('water_level', 5, 2);
            $table->string('water_status'); // 'Normal', 'Rendah', 'Hampir habis', 'Pengisian'
            $table->string('pump_status')->default('OFF'); // 'ON', 'OFF'
            $table->string('state_desc'); // e.g. 'Pompa Air: ON – Sedang Mengisi'
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_pump_logs');
    }
};
