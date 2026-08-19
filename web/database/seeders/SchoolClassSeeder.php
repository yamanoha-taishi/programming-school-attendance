<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // A1・A2・A3は、それぞれ別日程なので専用のsectionを1つずつ
        $sectionA1 = Section::where('name', 'A1')->where('weekday', '火')->firstOrFail();
        $sectionA2 = Section::where('name', 'A2')->where('weekday', '月')->firstOrFail();
        $sectionA3 = Section::where('name', 'A3')->where('weekday', '火')->firstOrFail();

        // B1〜B6はまとめて1回で実施するので、同じsectionを共有する
        $sectionB = Section::where('name', 'B')->where('weekday', '月')->firstOrFail();

        $schoolClasses = [
            ['section_id' => $sectionA1->id, 'code' => 'A1', 'display_name' => '基礎クラス1組'],
            ['section_id' => $sectionA2->id, 'code' => 'A2', 'display_name' => '基礎クラス2組'],
            ['section_id' => $sectionA3->id, 'code' => 'A3', 'display_name' => '基礎クラス3組'],
            ['section_id' => $sectionB->id, 'code' => 'B1', 'display_name' => '応用クラス1組'],
            ['section_id' => $sectionB->id, 'code' => 'B2', 'display_name' => '応用クラス2組'],
            ['section_id' => $sectionB->id, 'code' => 'B3', 'display_name' => '応用クラス3組'],
            ['section_id' => $sectionB->id, 'code' => 'B4', 'display_name' => '応用クラス4組'],
            ['section_id' => $sectionB->id, 'code' => 'B5', 'display_name' => '応用クラス5組'],
            ['section_id' => $sectionB->id, 'code' => 'B6', 'display_name' => '応用クラス6組'],
        ];

        foreach ($schoolClasses as $schoolClass) {
            SchoolClass::create($schoolClass);
        }
    }
}
