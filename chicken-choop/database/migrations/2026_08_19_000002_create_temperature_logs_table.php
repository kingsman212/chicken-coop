<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperature_logs', function (Blueprint $table) {
            $table->id();
            $table->decimal('temperature', 4, 2);
            $table->string('status'); // 'Normal', 'Terlalu Panas', 'Terlalu Dingin', 'Emergency'
            $table->string('lamp_status')->default('OFF'); // 'ON', 'OFF'
            $table->string('fan_status')->default('OFF');  // 'ON', 'OFF'
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_logs');
    }
};
