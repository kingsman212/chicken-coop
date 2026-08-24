<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\SystemSetting::instance();

        \App\Models\FeedingSchedule::firstOrCreate(
            ['time' => '06:00:00'],
            [
                'label' => 'Pemberian Pakan Pagi',
                'portion_grams' => 500,
                'is_active' => true,
            ]
        );

        \App\Models\FeedingSchedule::firstOrCreate(
            ['time' => '15:00:00'],
            [
                'label' => 'Pemberian Pakan Sore',
                'portion_grams' => 500,
                'is_active' => true,
            ]
        );
        $this->call(UserSeeder::class);
    }
}
