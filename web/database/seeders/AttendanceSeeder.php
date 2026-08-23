<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceSeeder extends Seeder
{
    // 欠席になる確率（残りが出席）
    private const ABSENCE_RATE = 10;

    // 出席のうち遅刻になる確率
    private const LATE_RATE = 10;

    // 欠席時のmakeup_typeの重み（合計100）
    private const MAKEUP_TYPE_WEIGHTS = [
        '振替' => 20,
        '30分前補講' => 40,
        '補講なし' => 10,
        '未定' => 30,
    ];

    // 直近何日分を「まだスタッフ未対応の可能性がある」とみなすか
    private const RECENT_DAYS = 3;

    // 直近の保護者からの事前連絡（欠席・遅刻）がスタッフ未対応（staff_id = null）のままになる確率
    private const UNPROCESSED_RATE = 50;

    // 欠席・遅刻時にnoteを残す確率
    private const NOTE_RATE = 30;

    private const ABSENCE_NOTES = [
        '体調不良のため欠席します',
        '発熱のため欠席します',
        '家庭の用事のため欠席します',
        '学校行事と重なったため欠席します',
        '交通機関の乱れのため欠席します',
    ];

    private const LATE_NOTES = [
        '電車遅延のため遅刻します',
        '学校が長引いたため遅刻します',
        '体調不良のため少し遅れます',
        '準備が遅れて遅刻します',
    ];

    private Carbon $today;

    private Carbon $recentCutoff;

    /** @var int[] */
    private array $staffIds = [];

    /** @var Collection<int, Collection<int, Lesson>> section_id => 過去のlessons */
    private Collection $lessonsBySection;

    /** @var Collection<int, Collection<int, Lesson>> lesson_plan_id => 過去のlessons */
    private Collection $lessonsByPlan;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->today = Carbon::today();
        $this->recentCutoff = $this->today->copy()->subDays(self::RECENT_DAYS);
        $this->staffIds = Staff::pluck('id')->all();

        $pastLessons = Lesson::where('date', '<', $this->today->toDateString())
            ->orderBy('date')
            ->get(['id', 'lesson_plan_id', 'section_id', 'date']);

        $this->lessonsBySection = $pastLessons->groupBy('section_id');
        $this->lessonsByPlan = $pastLessons->groupBy('lesson_plan_id');

        Student::all()->each(function (Student $student) {
            $lessons = $this->lessonsBySection->get($student->section_id, collect());

            foreach ($lessons as $lesson) {
                if ($this->isOnLeave($student, $lesson->date)) {
                    continue;
                }

                Attendance::factory()->create($this->buildAttributes($student, $lesson));
            }
        });
    }

    /**
     * 授業日が生徒の休会期間（leave_from〜leave_until）に重なっているか。
     * leave_untilがNULLの場合はleave_from以降ずっと休会中として扱う。
     */
    private function isOnLeave(Student $student, Carbon $lessonDate): bool
    {
        if ($student->leave_from === null) {
            return false;
        }

        if ($lessonDate->lt($student->leave_from)) {
            return false;
        }

        if ($student->leave_until === null) {
            return true;
        }

        return $lessonDate->lte($student->leave_until);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(Student $student, Lesson $lesson): array
    {
        $isAbsent = fake()->boolean(self::ABSENCE_RATE);
        $status = $isAbsent ? '欠席' : '出席';
        $isLate = ! $isAbsent && fake()->boolean(self::LATE_RATE);

        $makeupType = null;
        $makeupLessonId = null;

        if ($isAbsent) {
            $makeupType = $this->pickMakeupType();

            if ($makeupType === '振替') {
                $makeupLessonId = $this->pickTransferLessonId($lesson);
            } elseif ($makeupType === '30分前補講') {
                $makeupLessonId = $this->pickCatchUpLessonId($lesson);
            }

            // 振替・30分前補講の候補が見つからなかった場合は「未定」に倒す
            if (in_array($makeupType, ['振替', '30分前補講'], true) && $makeupLessonId === null) {
                $makeupType = '未定';
            }
        }

        // 保護者からの事前連絡（欠席連絡・遅刻連絡）に該当するか。
        // 普通に出席しただけの記録は、保護者の事前連絡を経由しないので対象外。
        $guardianNotified = $isAbsent || $isLate;
        $isRecent = $lesson->date->gte($this->recentCutoff);

        $staffId = ($isRecent && $guardianNotified && fake()->boolean(self::UNPROCESSED_RATE))
            ? null
            : fake()->randomElement($this->staffIds);

        $note = null;

        if ($guardianNotified && fake()->boolean(self::NOTE_RATE)) {
            $note = $isAbsent
                ? fake()->randomElement(self::ABSENCE_NOTES)
                : fake()->randomElement(self::LATE_NOTES);
        }

        return [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'makeup_lesson_id' => $makeupLessonId,
            'staff_id' => $staffId,
            'status' => $status,
            'is_late' => $isLate,
            'makeup_type' => $makeupType,
            'note' => $note,
        ];
    }

    private function pickMakeupType(): string
    {
        $roll = fake()->numberBetween(1, 100);
        $cumulative = 0;

        foreach (self::MAKEUP_TYPE_WEIGHTS as $type => $weight) {
            $cumulative += $weight;

            if ($roll <= $cumulative) {
                return $type;
            }
        }

        return '未定';
    }

    /**
     * 振替先：同じlesson_plan_id（＝同じ内容）で、別のsectionの過去のlessonから選ぶ。
     */
    private function pickTransferLessonId(Lesson $lesson): ?int
    {
        $candidates = $this->lessonsByPlan->get($lesson->lesson_plan_id, collect())
            ->where('id', '!=', $lesson->id);

        return $candidates->isEmpty() ? null : $candidates->random()->id;
    }

    /**
     * 30分前補講先：本人のsectionで、欠席した回より後の日付の直近のlessonを選ぶ。
     */
    private function pickCatchUpLessonId(Lesson $lesson): ?int
    {
        $candidates = $this->lessonsBySection->get($lesson->section_id, collect())
            ->where('date', '>', $lesson->date)
            ->sortBy('date');

        return $candidates->isEmpty() ? null : $candidates->first()->id;
    }
}
