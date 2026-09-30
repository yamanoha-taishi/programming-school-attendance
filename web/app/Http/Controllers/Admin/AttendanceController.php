<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceDisplayStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $validated['date'] ?? $this->defaultDate();

        $lessons = Lesson::with(['section', 'lessonPlan.schoolClass'])
            ->whereDate('date', $date)
            ->get();

        $students = Student::with('schoolClass')
            ->whereIn('section_id', $lessons->pluck('section_id')->unique())
            ->notOnLeave($date)
            ->orderBy('name_kana')
            ->get();

        $attendances = Attendance::whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->keyBy(fn (Attendance $attendance) => "{$attendance->student_id}-{$attendance->lesson_id}");

        $blocks = $lessons
            ->sortBy(fn (Lesson $lesson) => $lesson->section->start_time)
            ->groupBy('section_id')
            ->map(fn (Collection $sectionLessons) => $this->buildBlock($sectionLessons, $students, $attendances))
            ->values();

        return Inertia::render('admin/attendances/index', [
            'date' => $date,
            'blocks' => $blocks,
        ]);
    }

    public function update(Request $request, Lesson $lesson, Student $student): void
    {
        //
    }

    public function updateMakeup(Request $request, Attendance $attendance): void
    {
        //
    }

    /**
     * ?dateが指定されていない場合に表示する日付。
     * 今日以前で最も新しい授業日（今日が授業日なら今日、そうでなければ直近の過去の授業日）。
     * 授業日が1日も無い場合（年度開始前など）は今日。
     */
    private function defaultDate(): string
    {
        $today = Carbon::today()->toDateString();

        $latest = Lesson::whereDate('date', '<=', $today)->max('date');

        return $latest !== null
            ? Carbon::parse($latest)->toDateString()
            : $today;
    }

    /**
     * 1つの授業実施単位の授業回（応用クラスなら最大6件）から、画面の1ブロック分のデータを作る。
     *
     * @param  Collection<int, Lesson>  $sectionLessons  このブロックの授業回
     * @param  Collection<int, Student>  $students  その日の全ブロックの生徒
     * @param  Collection<string, Attendance>  $attendances  その日の出席記録（キーは「生徒ID-授業回ID」）
     * @return array<string, mixed>
     */
    private function buildBlock(Collection $sectionLessons, Collection $students, Collection $attendances): array
    {
        $section = $sectionLessons->firstOrFail()->section;

        $rows = $students
            ->where('section_id', $section->id)
            ->sortBy([
                fn (Student $a, Student $b) => $a->schoolClass->code <=> $b->schoolClass->code,
                fn (Student $a, Student $b) => $a->gradeOrder() <=> $b->gradeOrder(),
            ])
            ->map(fn (Student $student) => $this->buildRow($student, $sectionLessons, $attendances))
            ->filter()
            ->values();

        return [
            'sectionId' => $section->id,
            'title' => $this->blockTitle($sectionLessons),
            'startTime' => substr($section->start_time, 0, 5),
            'endTime' => substr($section->end_time, 0, 5),
            'rows' => $rows,
        ];
    }

    /**
     * 生徒1人分の行のデータを作る。
     * このブロックの授業回の中から、その生徒の授業クラスの授業回を探し、その授業回の出席記録から表示ステータスを決める。
     * その日に自分のクラスの授業回が無い生徒はnullを返す（行に出さない）。
     *
     * @param  Collection<int, Lesson>  $sectionLessons
     * @param  Collection<string, Attendance>  $attendances
     * @return array<string, mixed>|null
     */
    private function buildRow(Student $student, Collection $sectionLessons, Collection $attendances): ?array
    {
        $lesson = $sectionLessons->first(
            fn (Lesson $lesson) => $lesson->lessonPlan->school_class_id === $student->school_class_id
        );

        if ($lesson === null) {
            return null;
        }

        $attendance = $attendances->get("{$student->id}-{$lesson->id}");

        return [
            'studentId' => $student->id,
            'name' => $student->name,
            'schoolClassName' => $lesson->lessonPlan->schoolClass->display_name,
            'lessonId' => $lesson->id,
            'displayStatus' => AttendanceDisplayStatus::fromAttendance($attendance)->value,
        ];
    }

    /**
     * ブロックの見出しを返す。
     * 授業クラスが1つならその表示名（例：基礎クラス1組）。
     * 複数の授業クラスが同じ授業実施単位を使っている場合は、
     * 表示名の末尾の「◯組」を除いた共通部分（例：応用クラス）。
     *
     * @param  Collection<int, Lesson>  $lessons
     */
    private function blockTitle(Collection $lessons): string
    {
        $names = $lessons
            ->map(fn (Lesson $lesson) => $lesson->lessonPlan->schoolClass->display_name)
            ->unique();

        $firstName = $names->firstOrFail();

        if ($names->count() === 1) {
            return $firstName;
        }

        return preg_replace('/\d+組$/u', '', $firstName) ?? $firstName;
    }
}
