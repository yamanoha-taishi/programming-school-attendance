<?php

namespace Database\Seeders;

use App\Models\Staff;
use Database\Seeders\Support\JapaneseNames;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    private const STAFF_COUNT = 5;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= self::STAFF_COUNT; $i++) {
            $lastName = fake()->randomElement(JapaneseNames::STAFF_LAST_NAMES);
            $firstName = fake()->randomElement(JapaneseNames::STAFF_FIRST_NAMES);

            $staff = Staff::factory()->create([
                'member_code' => (string) (5000 + $i),
                'name' => "{$lastName['kanji']} {$firstName['kanji']}",
                'name_kana' => "{$lastName['kana']} {$firstName['kana']}",
            ]);
        }
    }
}
