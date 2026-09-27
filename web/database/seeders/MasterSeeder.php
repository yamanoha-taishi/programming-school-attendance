<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            SectionSeeder::class,
            SchoolClassSeeder::class,
            HolidaySeeder::class,
            LessonPlanSeeder::class,
            LessonSeeder::class,
        ]);
    }
}
