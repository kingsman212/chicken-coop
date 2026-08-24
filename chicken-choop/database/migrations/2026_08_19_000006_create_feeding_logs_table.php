<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeding_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feeding_schedule_id')->nullable()->constrained('feeding_schedules')->nullOnDelete();
            $table->string('schedule_label')->nullable();
            $table->string('source')->default('Manual'); // 'Automatic/Scheduled', 'Manual'
            $table->string('status')->default('Selesai'); // 'Menunggu jadwal', 'Sedang memberi pakan', 'Selesai'
            $table->integer('portion_grams')->default(500);
            $table->timestamp('fed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeding_logs');
    }
};
