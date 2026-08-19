<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidays = [
            // ゴールデンウィーク（2026-04-27〜2026-05-09、月〜土）
            ['section_id' => null, 'date' => '2026-04-27', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-04-28', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-04-29', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-04-30', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-01', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-02', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-04', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-05', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-06', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-07', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-08', 'reason' => 'ゴールデンウィーク'],
            ['section_id' => null, 'date' => '2026-05-09', 'reason' => 'ゴールデンウィーク'],

            // 夏休み（お盆を含む、2026-08-03〜2026-08-15、月〜土）
            ['section_id' => null, 'date' => '2026-08-03', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-04', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-05', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-06', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-07', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-08', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-10', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-11', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-12', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-13', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-14', 'reason' => '夏休み'],
            ['section_id' => null, 'date' => '2026-08-15', 'reason' => '夏休み'],

            // シルバーウィーク（2026-09-14〜2026-09-26、月〜土）
            ['section_id' => null, 'date' => '2026-09-14', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-15', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-16', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-17', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-18', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-19', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-21', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-22', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-23', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-24', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-25', 'reason' => 'シルバーウィーク'],
            ['section_id' => null, 'date' => '2026-09-26', 'reason' => 'シルバーウィーク'],

            // 年末年始（2026-12-28〜2027-01-09、月〜土、2週間）
            ['section_id' => null, 'date' => '2026-12-28', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2026-12-29', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2026-12-30', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2026-12-31', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-01', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-02', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-04', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-05', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-06', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-07', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-08', 'reason' => '年末年始'],
            ['section_id' => null, 'date' => '2027-01-09', 'reason' => '年末年始'],

            // 春休み（2026年度末、2027-03-22〜2027-04-03、月〜土、2週間）
            ['section_id' => null, 'date' => '2027-03-22', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-23', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-24', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-25', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-26', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-27', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-29', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-30', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-03-31', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-04-01', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-04-02', 'reason' => '春休み'],
            ['section_id' => null, 'date' => '2027-04-03', 'reason' => '春休み'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::create($holiday);
        }
    }
}
