<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceDisplayStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Lesson;
use App\Models\Section;
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

        // 休んだ日の行に補講予定の日付を出すため、補講先の授業回も一緒に読み込む
        $attendances = Attendance::with('makeupLesson')
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->keyBy(fn (Attendance $attendance) => "{$attendance->student_id}-{$attendance->lesson_id}");

        // その日の授業回に、振替・30分前補講で「来る側」の欠席記録（クエリ1回）
        // 生徒が論理削除されている記録は、生徒の情報を表示できないので除く
        $makeupAttendances = Attendance::with(['lesson', 'student.schoolClass'])
            ->whereIn('makeup_lesson_id', $lessons->pluck('id'))
            ->whereHas('student')
            ->get();

        $makeupCandidates = $this->makeupCandidates($lessons, $date);

        $blocks = $lessons
            ->sortBy(fn (Lesson $lesson) => $lesson->section->start_time)
            ->groupBy('section_id')
            ->map(fn (Collection $sectionLessons) => $this->buildBlock($sectionLessons, $students, $attendances, $makeupAttendances, $makeupCandidates))
            ->values();

        // 授業計画上の第何回か。休校は週単位で決まるため、その日の授業回はふつう同じ回数になる。
        // 万一ずれていた場合（授業実施単位ごとの休校日を登録した場合など）に備えて、複数あれば小さい順にすべて渡す
        $lessonNos = $lessons
            ->map(fn (Lesson $lesson) => $lesson->lessonPlan->no)
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('admin/attendances/index', [
            'date' => $date,
            'lessonDates' => $this->lessonDates(),
            'upcomingHolidays' => $this->upcomingHolidays(),
            // まだ来ていない日か。未来の日付では、前もって登録できる欠席（と補講予定）だけを受け付ける
            'isFuture' => $date > Carbon::today()->toDateString(),
            'lessonNos' => $lessonNos,
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

        // まだ来ていない授業回には、出席は記録できない（欠席と補講予定は、前もって登録できる）
        if ($validated['status'] === '出席' && $lesson->date->isAfter(Carbon::today())) {
            throw ValidationException::withMessages([
                'status' => 'まだ来ていない授業回には、出席を記録できません。',
            ]);
        }

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

        // 補講先の授業回がまだ来ていない場合は、補講先の出欠は記録できない
        if ($attendance->makeupLesson?->date->isAfter(Carbon::today())) {
            throw ValidationException::withMessages([
                'makeup_status' => 'まだ来ていない補講先の授業回には、出欠を記録できません。',
            ]);
        }

        $attendance->update(['makeup_status' => $validated['makeup_status']]);

        return back();
    }

    /**
     * 補講先の授業回として選べるか確認する。候補の出し方（makeupCandidates）と同じルールで判定する。
     * - 基礎クラス：同じ授業クラスの、休んだ授業回とは別の授業回
     * - 応用クラス：応用クラスの回で、休んだ回と同じ日・同じ時間の回ではないもの（組は問わない）
     * どちらも曜日（授業実施単位）は問わない。特別な調整（別の曜日の回の内容を入れ替えて補講するなど）も登録できるようにするため。
     */
    private function ensureValidMakeupLesson(Lesson $lesson, int $makeupLessonId): void
    {
        $makeupLesson = Lesson::with(['section', 'lessonPlan'])->findOrFail($makeupLessonId);

        $isValid = $lesson->section->isAdvanced()
            ? $makeupLesson->section->isAdvanced() && ! $this->isSameSession($makeupLesson, $lesson)
            : $makeupLesson->id !== $lesson->id
                && $makeupLesson->lessonPlan->school_class_id === $lesson->lessonPlan->school_class_id;

        if (! $isValid) {
            throw ValidationException::withMessages([
                'makeup_lesson_id' => '補講先に選べない授業回です。',
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
     * 日付の切り替え（◀ ▶・日付一覧）に使う授業日の一覧（新しい順）。
     * 今日以前の授業日と、未来の授業日のうち記録が登録されている日
     * （保護者からの欠席・遅刻の事前連絡がある日と、振替・30分前補講で来る予定の生徒がいる日）。
     * 未来の日付は前もって予定を確かめるために出す。記録が無い未来の日付は出さない。
     *
     * @return Collection<int, string>
     */
    private function lessonDates(): Collection
    {
        $today = Carbon::today()->toDateString();

        return Lesson::where(fn ($query) => $query
            ->whereDate('date', '<=', $today)
            ->orWhere(fn ($query) => $query
                ->whereDate('date', '>', $today)
                ->where(fn ($query) => $query
                    ->whereHas('attendances')
                    ->orWhereHas('makeupAttendances'))))
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();
    }

    /**
     * 今後の休校（全体の休校だけ）を、期間ごとにまとめたもの（近い順に最大3件）。
     * 日付の一覧のモーダルで、次に休みになる週を確かめるために使う。
     * 休校日は1日ずつ登録されているので、同じ理由で続いている日を1つの期間にまとめる。
     * 授業が無い日曜日などは登録されていないため、前の日から2日以内なら続いているとみなす。
     *
     * @return list<array{reason: string, from: string, until: string}>
     */
    private function upcomingHolidays(): array
    {
        $holidays = Holiday::whereNull('section_id')
            ->whereDate('date', '>=', Carbon::today()->toDateString())
            ->orderBy('date')
            ->get();

        $periods = [];

        foreach ($holidays as $holiday) {
            $last = array_key_last($periods);

            if ($last !== null
                && $periods[$last]['reason'] === $holiday->reason
                && Carbon::parse($periods[$last]['until'])->diffInDays($holiday->date) <= 2) {
                $periods[$last]['until'] = $holiday->date->toDateString();

                continue;
            }

            $periods[] = [
                'reason' => $holiday->reason,
                'from' => $holiday->date->toDateString(),
                'until' => $holiday->date->toDateString(),
            ];
        }

        return array_slice($periods, 0, 3);
    }

    /**
     * その日のブロック（授業実施単位）ごとの、補講先（振替・30分前補講）の候補。キーは授業実施単位のID。
     * ブロックの生徒がその日の授業を休んだときの候補で、同じブロックの生徒はみんな同じ候補になる。
     * - 基礎クラスの授業実施単位は1つの授業クラスだけが使うため、その日の授業回は1件で、その授業回の候補になる
     * - 応用クラスはB1〜B6の授業回があっても、どの組の回でも補講を受けられるため、候補は組によらず同じになる
     * 休んだ日の1週間前〜4週間後の授業回から、基礎クラス・応用クラスでそれぞれ次のように選ぶ（曜日は問わない）。
     * - 基礎クラス：同じ授業クラスの授業回（休んだ授業回そのものは除く）
     * - 応用クラス：応用クラス（授業実施単位「B」）の回なら、どの組の回でもよい（休んだ回と同じ日・同じ時間の回は除く）
     *
     * @param  Collection<int, Lesson>  $lessons
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private function makeupCandidates(Collection $lessons, string $date): Collection
    {
        $from = Carbon::parse($date)->subWeek()->toDateString();
        $until = Carbon::parse($date)->addWeeks(4)->toDateString();

        // 期間内の授業回のうち、その日の授業回と同じクラスのものと、応用クラスのものをまとめて取ってくる（クエリ1回）
        $candidates = Lesson::with(['section', 'lessonPlan.schoolClass'])
            ->where(fn ($query) => $query
                ->whereHas('lessonPlan', fn ($query) => $query->whereIn(
                    'school_class_id',
                    $lessons->pluck('lessonPlan.school_class_id')->unique(),
                ))
                ->orWhereHas('section', fn ($query) => $query->where('name', Section::ADVANCED_NAME)))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $until)
            ->get()
            ->sortBy([
                fn (Lesson $a, Lesson $b) => $a->date <=> $b->date,
                fn (Lesson $a, Lesson $b) => $a->section->start_time <=> $b->section->start_time,
                fn (Lesson $a, Lesson $b) => $a->lessonPlan->schoolClass->code <=> $b->lessonPlan->schoolClass->code,
            ]);

        return $lessons
            ->groupBy('section_id')
            ->map(function (Collection $sectionLessons) use ($candidates) {
                $lesson = $sectionLessons->firstOrFail();

                return $lesson->section->isAdvanced()
                    ? $this->advancedMakeupCandidates($lesson, $candidates)
                    : $this->basicMakeupCandidates($lesson, $candidates);
            });
    }

    /**
     * 基礎クラスの補講先の候補。同じ授業クラスの授業回から、休んだ授業回そのものを除いたもの。
     * 基礎クラスはその回でやる授業内容が決まっているため、振替で探しやすいよう、同じ内容の授業回にsameContentで印を付ける。
     *
     * @param  Collection<int, Lesson>  $candidates  期間内の授業回（日付・開始時刻順）
     * @return Collection<int, array<string, mixed>>
     */
    private function basicMakeupCandidates(Lesson $lesson, Collection $candidates): Collection
    {
        return $candidates
            ->filter(fn (Lesson $candidate) => $candidate->id !== $lesson->id
                && $candidate->lessonPlan->school_class_id === $lesson->lessonPlan->school_class_id)
            ->map(fn (Lesson $candidate) => $this->makeupCandidateItem(
                $candidate,
                $candidate->lessonPlan->schoolClass->display_name,
                $candidate->lessonPlan->no,
                $candidate->lesson_plan_id === $lesson->lesson_plan_id,
            ))
            ->values();
    }

    /**
     * 応用クラスの補講先の候補。応用クラスの回（同じ日・同じ時間の授業回をまとめたもの）から、休んだ回を除いたもの。
     * 応用クラス（B1〜B6）は同じ時間に一緒に授業をし、生徒が自分のペースで進めるため、どの組の回でも補講を受けられる。
     * 回でやる授業内容が決まっていないので、同じ内容の印（sameContent）は付けない。
     * 補講先として保存する授業回は、その回の授業回のどれか1つでよい（一覧画面では、来る側の行はその回のブロックに表示される）。
     *
     * @param  Collection<int, Lesson>  $candidates  期間内の授業回（日付・開始時刻・組の順）
     * @return Collection<int, array<string, mixed>>
     */
    private function advancedMakeupCandidates(Lesson $lesson, Collection $candidates): Collection
    {
        return $candidates
            ->filter(fn (Lesson $candidate) => $candidate->section->isAdvanced()
                && ! $this->isSameSession($candidate, $lesson))
            // 同じ日・同じ授業実施単位の授業回（B1〜B6が一緒に授業をする回）を1つにまとめる
            ->groupBy(fn (Lesson $candidate) => "{$candidate->date->toDateString()}-{$candidate->section_id}")
            ->map(function (Collection $sessionLessons) {
                $candidate = $sessionLessons->firstOrFail();
                $displayName = $candidate->lessonPlan->schoolClass->display_name;

                // 組を付けず「応用クラス」と表示する（一覧画面のブロックの見出しと同じ）
                $title = preg_replace('/\d+組$/u', '', $displayName) ?? $displayName;

                // 授業計画上の回数は参考として渡す（休校は週単位で決まるため、同じ回の授業回はどの組でも同じ回数になる）。
                // 応用クラスは生徒が自分のペースで進めるため、同じ回の印（sameContent）は付けない
                return $this->makeupCandidateItem($candidate, $title, $candidate->lessonPlan->no, false);
            })
            ->values();
    }

    /**
     * 補講先の候補1件分のデータ。
     *
     * @param  int  $lessonNo  授業計画上の第何回か
     * @return array<string, mixed>
     */
    private function makeupCandidateItem(Lesson $candidate, string $title, int $lessonNo, bool $sameContent): array
    {
        return [
            'lessonId' => $candidate->id,
            'date' => $candidate->date->toDateString(),
            'weekday' => $candidate->section->weekday,
            'startTime' => substr($candidate->section->start_time, 0, 5),
            'endTime' => substr($candidate->section->end_time, 0, 5),
            'title' => $title,
            'lessonNo' => $lessonNo,
            'sameContent' => $sameContent,
        ];
    }

    /**
     * 2つの授業回が、同じ日・同じ授業実施単位の回（応用クラスなら、B1〜B6が一緒に授業をする同じ回）か。
     */
    private function isSameSession(Lesson $a, Lesson $b): bool
    {
        return $a->section_id === $b->section_id && $a->date->isSameDay($b->date);
    }

    /**
     * 1つの授業実施単位の授業回（応用クラスなら最大6件）から、画面の1ブロック分のデータを作る。
     *
     * @param  Collection<int, Lesson>  $sectionLessons  このブロックの授業回
     * @param  Collection<int, Student>  $students  その日の全ブロックの生徒
     * @param  Collection<string, Attendance>  $attendances  その日の出席記録（キーは「生徒ID-授業回ID」）
     * @param  Collection<int, Attendance>  $makeupAttendances  その日の全ブロックに補講で来る欠席記録
     * @param  Collection<int, Collection<int, array<string, mixed>>>  $makeupCandidates  ブロックごとの補講先の候補（キーは授業実施単位のID）
     * @return array<string, mixed>
     */
    private function buildBlock(Collection $sectionLessons, Collection $students, Collection $attendances, Collection $makeupAttendances, Collection $makeupCandidates): array
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
            // このブロックの生徒が欠席したときの補講先の候補
            'makeupCandidates' => $makeupCandidates->get($section->id, collect()),
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
            // 授業計画上の第何回か（補講先を選ぶモーダルで、どの欠席の補講かを示すのに使う）
            'lessonNo' => $lesson->lessonPlan->no,
            'displayStatus' => AttendanceDisplayStatus::fromAttendance($attendance)->value,
            'status' => $attendance?->status,
            // 欠席したときの補講予定（振替／30分前補講／未定／補講なし）と、補講先の日付・補講先での出欠
            'makeupType' => $attendance?->makeup_type,
            'makeupDate' => $attendance?->makeupLesson?->date->toDateString(),
            'makeupStatus' => $attendance?->makeup_status,
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
