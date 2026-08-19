<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            // 月曜
            ['name' => 'A2', 'weekday' => '月', 'start_time' => '15:00', 'end_time' => '16:00'],
            ['name' => 'B',  'weekday' => '月', 'start_time' => '16:30', 'end_time' => '18:00'],

            // 火曜
            ['name' => 'A1', 'weekday' => '火', 'start_time' => '15:00', 'end_time' => '16:00'],
            ['name' => 'A3', 'weekday' => '火', 'start_time' => '16:30', 'end_time' => '17:30'],

            // 水曜
            ['name' => 'A2', 'weekday' => '水', 'start_time' => '15:30', 'end_time' => '16:30'],
            ['name' => 'B',  'weekday' => '水', 'start_time' => '17:00', 'end_time' => '18:30'],

            // 木曜
            ['name' => 'B',  'weekday' => '木', 'start_time' => '16:00', 'end_time' => '17:30'],

            // 金曜
            ['name' => 'A1', 'weekday' => '金', 'start_time' => '15:00', 'end_time' => '16:00'],
            ['name' => 'A2', 'weekday' => '金', 'start_time' => '16:30', 'end_time' => '17:30'],
            ['name' => 'A3', 'weekday' => '金', 'start_time' => '18:00', 'end_time' => '19:00'],

            // 土曜
            ['name' => 'A1', 'weekday' => '土', 'start_time' => '10:00', 'end_time' => '11:00'],
            ['name' => 'A2', 'weekday' => '土', 'start_time' => '11:30', 'end_time' => '12:30'],
            ['name' => 'A3', 'weekday' => '土', 'start_time' => '13:00', 'end_time' => '14:00'],
            ['name' => 'B',  'weekday' => '土', 'start_time' => '14:30', 'end_time' => '16:00'],
        ];

        foreach ($sections as $section) {
            Section::create($section);
        }
    }
}
