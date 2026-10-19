<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceDisplayStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        // その日の授業回に、振替・30分前補講で「来る側」の欠席記録（クエリ1回）
        // 生徒が論理削除されている記録は、生徒の情報を表示できないので除く
        $makeupAttendances = Attendance::with(['lesson', 'student.schoolClass'])
            ->whereIn('makeup_lesson_id', $lessons->pluck('id'))
            ->whereHas('student')
            ->get();

        $blocks = $lessons
            ->sortBy(fn (Lesson $lesson) => $lesson->section->start_time)
            ->groupBy('section_id')
            ->map(fn (Collection $sectionLessons) => $this->buildBlock($sectionLessons, $students, $attendances, $makeupAttendances))
            ->values();

        return Inertia::render('admin/attendances/index', [
            'date' => $date,
            'lessonDates' => $this->lessonDates(),
            'blocks' => $blocks,
            'daySummary' => [
                'present' => $blocks->sum('summary.present'),
                'absent' => $blocks->sum('summary.absent'),
                'unrecorded' => $blocks->sum('summary.unrecorded'),
            ],
        ]);
    }

    public function update(Request $request, Lesson $lesson, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['出席', '欠席'])],
            'makeup_type' => ['required_if:status,欠席', 'nullable', Rule::in(['振替', '30分前補講', '未定', '補講なし'])],
            'makeup_lesson_id' => ['required_if:makeup_type,振替,30分前補講', 'nullable', 'integer', 'exists:lessons,id'],
        ]);

        // この授業回が、生徒の所属する授業実施単位・授業クラスのものでなければ受け付けない
        abort_unless(
            $student->section_id === $lesson->section_id
                && $student->school_class_id === $lesson->lessonPlan->school_class_id,
            404,
        );

        $isAbsent = $validated['status'] === '欠席';
        $makeupType = $isAbsent ? $validated['makeup_type'] : null;

        // 振替・30分前補講のときだけ、補講先の授業回を保存する
        $makeupLessonId = in_array($makeupType, ['振替', '30分前補講'], true)
            ? $validated['makeup_lesson_id']
            : null;

        if ($makeupLessonId !== null) {
            $this->ensureValidMakeupLesson($lesson, (int) $makeupLessonId);
        }

        /** @var Staff $staff */
        $staff = $request->user('staff');

        // この生徒・この授業回のレコードがあれば更新、なければ作成
        Attendance::updateOrCreate(
            ['student_id' => $student->id, 'lesson_id' => $lesson->id],
            [
                'status' => $validated['status'],
                // 出席ならnull、欠席なら選んだ補講予定
                'makeup_type' => $makeupType,
                'makeup_lesson_id' => $makeupLessonId,
                // 補講予定を決め直したので、補講先での出欠は未記録に戻す
                'makeup_status' => null,
                'staff_id' => $staff->id,
            ],
        );

        return back();
    }

    public function updateMakeup(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'makeup_status' => ['required', Rule::in(['出席', '欠席'])],
        ]);

        // 振替・30分前補講で、補講先の授業回が決まっている記録だけ受け付ける
        if (! in_array($attendance->makeup_type, ['振替', '30分前補講'], true)
            || $attendance->makeup_lesson_id === null) {
            throw ValidationException::withMessages([
                'makeup_status' => '補講予定が無い記録には、補講先の出欠を記録できません。',
            ]);
        }

        $attendance->update(['makeup_status' => $validated['makeup_status']]);

        return back();
    }

    /**
     * 補講先の授業回が、休んだ授業回と同じ授業クラスのもので、休んだ授業回そのものでないか確認する。
     * 曜日（授業実施単位）は問わない。特別な調整（別の曜日の回の内容を入れ替えて補講するなど）も登録できるようにするため。
     */
    private function ensureValidMakeupLesson(Lesson $lesson, int $makeupLessonId): void
    {
        $makeupLesson = Lesson::with('lessonPlan')->findOrFail($makeupLessonId);

        if ($makeupLesson->id === $lesson->id
            || $makeupLesson->lessonPlan->school_class_id !== $lesson->lessonPlan->school_class_id) {
            throw ValidationException::withMessages([
                'makeup_lesson_id' => '補講先には、同じ授業クラスの別の授業回を選んでください。',
            ]);
        }
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
     * 日付の切り替え（◀ ▶・日付一覧）に使う、今日以前で授業がある日付の一覧（新しい順）。
     *
     * @return Collection<int, string>
     */
    private function lessonDates(): Collection
    {
        return Lesson::whereDate('date', '<=', Carbon::today()->toDateString())
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();
    }

    /**
     * 1つの授業実施単位の授業回（応用クラスなら最大6件）から、画面の1ブロック分のデータを作る。
     *
     * @param  Collection<int, Lesson>  $sectionLessons  このブロックの授業回
     * @param  Collection<int, Student>  $students  その日の全ブロックの生徒
     * @param  Collection<string, Attendance>  $attendances  その日の出席記録（キーは「生徒ID-授業回ID」）
     * @param  Collection<int, Attendance>  $makeupAttendances  その日の全ブロックに補講で来る欠席記録
     * @return array<string, mixed>
     */
    private function buildBlock(Collection $sectionLessons, Collection $students, Collection $attendances, Collection $makeupAttendances): array
    {
        $section = $sectionLessons->firstOrFail()->section;

        // 全ブロックの「来る側」の記録から、補講先がこのブロックの授業回のどれかになっているものだけを取り出す
        $blockMakeups = $makeupAttendances
            ->whereIn('makeup_lesson_id', $sectionLessons->pluck('id'))
            ->sortBy([
                fn (Attendance $a, Attendance $b) => $a->student->schoolClass->code <=> $b->student->schoolClass->code,
                fn (Attendance $a, Attendance $b) => $a->student->gradeOrder() <=> $b->student->gradeOrder(),
            ]);

        $regularRows = $students
            ->where('section_id', $section->id)
            ->sortBy([
                fn (Student $a, Student $b) => $a->schoolClass->code <=> $b->schoolClass->code,
                fn (Student $a, Student $b) => $a->gradeOrder() <=> $b->gradeOrder(),
            ])
            ->map(fn (Student $student) => $this->buildRow($student, $sectionLessons, $attendances, $blockMakeups))
            ->filter();

        $makeupRows = $blockMakeups->map(fn (Attendance $attendance) => $this->buildMakeupRow($attendance));

        $rows = $regularRows->concat($makeupRows)->values();

        return [
            'sectionId' => $section->id,
            'title' => $this->blockTitle($sectionLessons),
            'startTime' => substr($section->start_time, 0, 5),
            'endTime' => substr($section->end_time, 0, 5),
            'rows' => $rows,
            'summary' => $this->summarize($rows),
        ];
    }

    /**
     * 生徒1人分の行のデータを作る。
     * このブロックの授業回の中から、その生徒の授業クラスの授業回を探し、その授業回の出席記録から表示ステータスを決める。
     * その日に自分のクラスの授業回が無い生徒はnullを返す（行に出さない）。
     *
     * @param  Collection<int, Lesson>  $sectionLessons
     * @param  Collection<string, Attendance>  $attendances
     * @param  Collection<int, Attendance>  $blockMakeups  このブロックに補講で来る欠席記録
     * @return array<string, mixed>|null
     */
    private function buildRow(Student $student, Collection $sectionLessons, Collection $attendances, Collection $blockMakeups): ?array
    {
        $lesson = $sectionLessons->first(
            fn (Lesson $lesson) => $lesson->lessonPlan->school_class_id === $student->school_class_id
        );

        if ($lesson === null) {
            return null;
        }

        $attendance = $attendances->get("{$student->id}-{$lesson->id}");

        return [
            'kind' => 'regular',
            'attendanceId' => $attendance?->id,
            'studentId' => $student->id,
            'name' => $student->name,
            'nameKana' => $student->name_kana,
            'schoolClassName' => $lesson->lessonPlan->schoolClass->display_name,
            'lessonId' => $lesson->id,
            'displayStatus' => AttendanceDisplayStatus::fromAttendance($attendance)->value,
            'status' => $attendance?->status,
            // 同じブロックの末尾に、この生徒の補講の行もあるか（自分のクラスの回に30分前補講で来る場合）
            'hasMakeup' => $blockMakeups->contains('student_id', $student->id),
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

    /**
     * 振替・30分前補講でこのブロックに来る生徒の行を、元の欠席記録から作る。
     *
     * @return array<string, mixed>
     */
    private function buildMakeupRow(Attendance $attendance): array
    {
        return [
            'kind' => 'makeup',
            // 補講先での出欠を記録するとき（updateMakeupルート）に使う、元の欠席記録のID
            'attendanceId' => $attendance->id,
            'studentId' => $attendance->student_id,
            'name' => $attendance->student->name,
            'nameKana' => $attendance->student->name_kana,
            'schoolClassName' => $attendance->student->schoolClass->display_name,
            'lessonId' => $attendance->makeup_lesson_id,
            'makeupType' => $attendance->makeup_type,
            // 何日の欠席分の補講か（例：2026-09-24。画面では「9/24欠席分」と表示する）
            'absentDate' => $attendance->lesson->date->toDateString(),
            'makeupStatus' => $attendance->makeup_status,
        ];
    }

    /**
     * 行を「出席・欠席・未記録」に分けて数える。
     * 通常の行は status、来る側の行は makeupStatus で判定する。
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{present: int, absent: int, unrecorded: int}
     */
    private function summarize(Collection $rows): array
    {
        $statuses = $rows->map(
            fn (array $row) => $row['kind'] === 'regular' ? $row['status'] : $row['makeupStatus']
        );

        return [
            'present' => $statuses->filter(fn ($status) => $status === '出席')->count(),
            'absent' => $statuses->filter(fn ($status) => $status === '欠席')->count(),
            'unrecorded' => $statuses->filter(fn ($status) => $status === null)->count(),
        ];
    }
}
