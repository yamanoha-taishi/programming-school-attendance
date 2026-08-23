<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolClasses = [
            ['code' => 'A1', 'display_name' => '基礎クラス1組'],
            ['code' => 'A2', 'display_name' => '基礎クラス2組'],
            ['code' => 'A3', 'display_name' => '基礎クラス3組'],
            ['code' => 'B1', 'display_name' => '応用クラス1組'],
            ['code' => 'B2', 'display_name' => '応用クラス2組'],
            ['code' => 'B3', 'display_name' => '応用クラス3組'],
            ['code' => 'B4', 'display_name' => '応用クラス4組'],
            ['code' => 'B5', 'display_name' => '応用クラス5組'],
            ['code' => 'B6', 'display_name' => '応用クラス6組'],
        ];

        foreach ($schoolClasses as $schoolClass) {
            SchoolClass::create($schoolClass);
        }
    }
}
