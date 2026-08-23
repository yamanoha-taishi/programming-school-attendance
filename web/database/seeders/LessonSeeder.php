<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\Lesson;
use App\Models\LessonPlan;
use App\Models\Section;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LessonSeeder extends Seeder
{
    // lesson_plans.noは1〜42なので、各授業実施単位（section）につき42件分の日付を用意する
    private const LESSON_COUNT = 42;

    // 学年度の起点（この日以降で、各曜日ごとに最初の該当曜日を探す）
    private const YEAR_START = '2026-04-01';

    private const WEEKDAY_ISO = [
        '月' => 1,
        '火' => 2,
        '水' => 3,
        '木' => 4,
        '金' => 5,
        '土' => 6,
        '日' => 7,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidayDates = Holiday::pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        foreach (Section::all() as $section) {
            $classCodes = $this->classCodesForSectionName($section->name);

            if ($classCodes === []) {
                continue;
            }

            $dates = $this->generateWeeklyDates($section->weekday, $holidayDates, self::LESSON_COUNT);

            foreach ($classCodes as $classCode) {
                $lessonPlans = LessonPlan::whereHas(
                    'schoolClass',
                    fn ($query) => $query->where('code', $classCode)
                )->orderBy('no')->get();

                foreach ($lessonPlans as $index => $lessonPlan) {
                    Lesson::create([
                        'lesson_plan_id' => $lessonPlan->id,
                        'section_id' => $section->id,
                        'date' => $dates[$index],
                    ]);
                }
            }
        }
    }

    /**
     * 授業実施単位名（sections.name）から、そのsectionを使う授業クラスのcode一覧を返す。
     * A1／A2／A3はクラス名と同名のsectionを1クラス専用で使い、
     * 「B」という名前のsectionはB1〜B6が共通で使う（B1〜B6クラスの授業実施単位はB）。
     *
     * @return string[]
     */
    private function classCodesForSectionName(string $sectionName): array
    {
        if ($sectionName === 'B') {
            return ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'];
        }

        if (in_array($sectionName, ['A1', 'A2', 'A3'], true)) {
            return [$sectionName];
        }

        return [];
    }

    /**
     * 指定した曜日の日付を、学年度の起点から週1回ペースで$count件分生成する。
     * $holidayDatesに含まれる日付（休校日）はスキップする。
     *
     * @param  string[]  $holidayDates
     * @return string[]
     */
    private function generateWeeklyDates(string $weekday, array $holidayDates, int $count): array
    {
        $targetIso = self::WEEKDAY_ISO[$weekday];
        $date = Carbon::parse(self::YEAR_START);

        while ($date->dayOfWeekIso !== $targetIso) {
            $date->addDay();
        }

        $dates = [];

        while (count($dates) < $count) {
            $dateString = $date->toDateString();

            if (! in_array($dateString, $holidayDates, true)) {
                $dates[] = $dateString;
            }

            $date->addWeek();
        }

        return $dates;
    }
}
