<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperature_emergencies', function (Blueprint $table) {
            $table->id();
            $table->decimal('temperature', 4, 2);
            $table->string('condition_type'); // 'Emergency Suhu Tinggi', 'Emergency Suhu Rendah'
            $table->decimal('threshold_breached', 4, 2);
            $table->string('active_actuators'); // e.g. 'Kipas ON', 'Lampu ON'
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->text('formatted_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_emergencies');
    }
};
