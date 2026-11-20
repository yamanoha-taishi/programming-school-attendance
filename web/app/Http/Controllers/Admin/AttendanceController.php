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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
    /**
     * 記録が無くても日付を選べるようにする、未来の期間（今日から何週間後までか）。
     * 電話などで受けた欠席の連絡を、スタッフが事前に登録できるようにするため。
     */
    private const ADVANCE_WEEKS = 5;

    /** 補講先の授業回を決める補講予定 */
    private const MAKEUP_TYPES_WITH_LESSON = ['振替', '30分前補講'];

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

        // 休んだ日の行に補講予定の日付を出すため、補講先の授業回も一緒に読み込む。
        // 行に「記録：〇〇」と出すため、記録したスタッフも読み込む（削除されたスタッフの名前も出す）
        $attendances = Attendance::with(['makeupLesson', 'staff' => fn ($query) => $query->withTrashed()])
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

        // その日の生徒の、補講予定（振替・30分前補講）が入っている欠席記録（補講先がその日の前後5週間のもの）。
        // 補講先を選ぶモーダルで、同じ日に入っているほかの欠席分の補講（1時間前補講になる、など）を示すのに使う
        $studentMakeups = Attendance::with(['lesson', 'makeupLesson'])
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('makeup_type', self::MAKEUP_TYPES_WITH_LESSON)
            ->whereHas('makeupLesson', fn (Builder $query) => $query
                ->whereDate('date', '>=', Carbon::parse($date)->subWeeks(5)->toDateString())
                ->whereDate('date', '<=', Carbon::parse($date)->addWeeks(5)->toDateString()))
            ->get()
            ->groupBy('student_id');

        $blocks = $lessons
            ->sortBy(fn (Lesson $lesson) => $lesson->section->start_time)
            ->groupBy('section_id')
            ->map(fn (Collection $sectionLessons) => $this->buildBlock($sectionLessons, $students, $attendances, $makeupAttendances, $makeupCandidates, $studentMakeups))
            ->values();

        // 授業計画上の第何回か。休校は週単位で決まるため、その日の授業回はふつう同じ回数になる。
        // 万一ずれていた場合（授業実施単位ごとの休校日を登録した場合など）に備えて、複数あれば小さい順にすべて渡す
        $lessonNos = $lessons
            ->map(fn (Lesson $lesson) => $lesson->lessonPlan->no)
            ->unique()
            ->sort()
            ->values();

        $today = Carbon::today();
        $advanceUntil = $this->advanceUntil();

        return Inertia::render('admin/attendances/index', [
            'date' => $date,
            'today' => $today->toDateString(),
            // ◀ ▶で移る、前後の選べる授業日（無ければnull）
            'prevDate' => $this->toDateString($this->selectableLessons($advanceUntil)->whereDate('date', '<', $date)->max('date')),
            'nextDate' => $this->toDateString($this->selectableLessons($advanceUntil)->whereDate('date', '>', $date)->min('date')),
            // 選べる授業日の最初と最後（日付選択のカレンダーで、前後の月へ移れる範囲を決めるのに使う）
            'firstSelectableDate' => $this->toDateString($this->selectableLessons($advanceUntil)->min('date')),
            'lastSelectableDate' => $this->toDateString($this->selectableLessons($advanceUntil)->max('date')),
            // 表示中の日付の年度のカレンダー（ほかの年度は、カレンダーで移ったときにcalendarで読み込む）。
            // 記録を保存したあとは画面側で受け取らない（except）ため、関数にして、受け取るときだけ作る
            'calendar' => fn () => $this->calendarFiscalYear(Carbon::parse($date), $advanceUntil),
            // 事前に登録できる期限の目安（日付選択のモーダルのお知らせに出す）
            'advanceUntil' => $advanceUntil,
            'advanceWeeks' => self::ADVANCE_WEEKS,
            'upcomingHolidays' => $this->upcomingHolidays(),
            // まだ来ていない日か。未来の日付では、事前に登録できる欠席（と補講予定）だけを受け付ける
            'isFuture' => $date > $today->toDateString(),
            'lessonNos' => $lessonNos,
            'blocks' => $blocks,
            // その日の出欠サマリー。その日の生徒全員（各ブロックに所属する生徒と、補講で来る生徒）を足す
            'daySummary' => [
                'present' => $blocks->sum('summary.present') + $blocks->sum('makeupSummary.present'),
                'absent' => $blocks->sum('summary.absent') + $blocks->sum('makeupSummary.absent'),
                'unrecorded' => $blocks->sum('summary.unrecorded') + $blocks->sum('makeupSummary.unrecorded'),
            ],
        ]);
    }

    /**
     * 日付選択のカレンダーで、まだ読み込んでいない年度の月に移ったときに、その月を含む年度の分のデータを返す（?month=YYYY-MM）。
     * すべての授業回を一度に渡すと年々増え続けるため、年度ごとに読み込む。
     */
    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::parse("{$validated['month']}-01")->startOfDay();

        return response()->json($this->calendarFiscalYear($month, $this->advanceUntil()));
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

        // 休会中の生徒は一覧に出さないので、記録も受け付けない
        if ($student->isOnLeave($lesson->date->toDateString())) {
            throw ValidationException::withMessages([
                'status' => '休会中の生徒には記録できません。',
            ]);
        }

        // まだ来ていない授業回には、出席は記録できない（欠席と補講予定は、事前に登録できる）
        if ($validated['status'] === '出席' && $lesson->date->isAfter(Carbon::today())) {
            throw ValidationException::withMessages([
                'status' => '授業の日になると記録できます。',
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
            $this->ensureMakeupLessonHasRoom($student, $lesson, (string) $makeupType, (int) $makeupLessonId);
        }

        /** @var Staff $staff */
        $staff = $request->user('staff');

        // 同じ補講予定（種類と補講先）を選び直したときは、補講先での出欠（振替済みなど）を残す。
        // 補講予定を変えたときだけ、補講先での出欠を未記録に戻す。
        // 補講先は、同じ日の同じ回なら同じとみなす（応用クラスは、B1〜B6のどの組の授業回でも同じ回）
        $current = Attendance::with('makeupLesson')->where('student_id', $student->id)->where('lesson_id', $lesson->id)->first();
        $currentMakeupLesson = $current?->makeupLesson;
        $isSameMakeup = $makeupLessonId !== null
            && $current !== null
            && $current->makeup_type === $makeupType
            && $currentMakeupLesson !== null
            && $this->isSameSession($currentMakeupLesson, Lesson::findOrFail((int) $makeupLessonId));

        // この生徒・この授業回のレコードがあれば更新、なければ作成
        Attendance::updateOrCreate(
            ['student_id' => $student->id, 'lesson_id' => $lesson->id],
            [
                'status' => $validated['status'],
                // 出席ならnull、欠席なら選んだ補講予定
                'makeup_type' => $makeupType,
                'makeup_lesson_id' => $makeupLessonId,
                'makeup_status' => $isSameMakeup ? $current->makeup_status : null,
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
                'makeup_status' => '授業の日になると記録できます。',
            ]);
        }

        // 同じ生徒が同じ日の同じ回に来る補講（1時間前補講・振替＋30分前補講）は、まとめて1回で記録する
        $makeupLesson = $attendance->makeupLesson;
        if ($makeupLesson === null) {
            abort(404);
        }

        $this->sameSessionMakeups($attendance->student_id, $makeupLesson)
            ->update(['makeup_status' => $validated['makeup_status']]);

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
     * 同じ生徒が同じ日の同じ回に入れられる補講の数を超えていないか確認する。
     * 振替は1回分まで、30分前補講は2回分（1時間前補講）まで。振替と30分前補講は一緒に受けられる。
     * 選び直しのときは、この欠席の記録そのものは数えない。
     */
    private function ensureMakeupLessonHasRoom(Student $student, Lesson $lesson, string $makeupType, int $makeupLessonId): void
    {
        $otherTypes = $this->sameSessionMakeups($student->id, Lesson::findOrFail($makeupLessonId))
            ->where('lesson_id', '!=', $lesson->id)
            ->pluck('makeup_type');

        if ($makeupType === '振替' && $otherTypes->contains('振替')) {
            throw ValidationException::withMessages([
                'makeup_lesson_id' => 'この日には、すでに別の欠席分の振替で来る予定です。',
            ]);
        }

        if ($makeupType === '30分前補講' && $otherTypes->filter(fn ($type) => $type === '30分前補講')->count() >= 2) {
            throw ValidationException::withMessages([
                'makeup_lesson_id' => 'この日には、すでに2回分の30分前補講（1時間前補講）が入っています。',
            ]);
        }
    }

    /**
     * 同じ生徒が、指定した補講先と同じ日の同じ回（応用クラスなら、B1〜B6が一緒に授業をする回）に入れている補講予定。
     *
     * @return Builder<Attendance>
     */
    private function sameSessionMakeups(int $studentId, Lesson $makeupLesson): Builder
    {
        return Attendance::where('student_id', $studentId)
            ->whereIn('makeup_type', self::MAKEUP_TYPES_WITH_LESSON)
            ->whereHas('makeupLesson', fn (Builder $query) => $query
                ->where('section_id', $makeupLesson->section_id)
                ->whereDate('date', $makeupLesson->date->toDateString()));
    }

    /**
     * 補講先の回を表すキー（「日付-授業実施単位ID」）。同じ日の同じ回に入っている補講をまとめるのに使う。
     */
    private function sessionKey(Lesson $lesson): string
    {
        return "{$lesson->date->toDateString()}-{$lesson->section_id}";
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
     * 事前に登録できる期限の目安（今日から5週間後）。
     */
    private function advanceUntil(): string
    {
        return Carbon::today()->addWeeks(self::ADVANCE_WEEKS)->toDateString();
    }

    /**
     * 日付の切り替え（◀ ▶・日付選択のモーダル）で選べる授業回。
     * - 事前に登録できる期限（今日から5週間後）までの授業回：今日以前の授業回と、電話などで受けた欠席を事前に登録するための未来の授業回
     * - それより先の授業回のうち、記録が登録されているもの（保護者からの欠席・遅刻の事前連絡がある回と、振替・30分前補講で来る予定の生徒がいる回）
     * 期限は目安で、それより先でも記録がある日を開けば、ほかの生徒の欠席も事前に登録できる。
     *
     * @param  string  $advanceUntil  事前に登録できる期限の日付
     * @return Builder<Lesson>
     */
    private function selectableLessons(string $advanceUntil): Builder
    {
        return Lesson::where(fn ($query) => $query
            ->whereDate('date', '<=', $advanceUntil)
            ->orWhereHas('attendances')
            ->orWhereHas('makeupAttendances'));
    }

    /**
     * DBから取り出した日付（文字列やCarbon）を「2026-10-03」の形にする。nullならnull。
     */
    private function toDateString(mixed $date): ?string
    {
        return $date === null ? null : Carbon::parse($date)->toDateString();
    }

    /**
     * 日付選択のカレンダーの、1年度分（4月〜翌3月）のデータ。
     * 今の年度で、事前に登録できる期限（今日から5週間後）が次の年度に入るとき（3月ごろ）は、その月まで延ばす。
     * カレンダーには月の最初と最後の週の、前後の月の日も出すため、最初の月の1日を含む週の月曜日〜最後の月の末日を含む週の日曜日の範囲で読み込む。
     *
     * @param  Carbon  $date  年度を決める日付
     * @return array{months: list<string>, lessonDates: list<string>, weekLessonNos: array<string, int>, holidayWeeks: array<string, string>}
     */
    private function calendarFiscalYear(Carbon $date, string $advanceUntil): array
    {
        // 年度の始まり（4月1日）と終わり（翌年の3月31日）
        $fiscalYear = $date->month >= 4 ? $date->year : $date->year - 1;
        $firstMonth = Carbon::parse("{$fiscalYear}-04-01");
        $lastMonth = $firstMonth->copy()->addMonths(11);

        // 今の年度（年度の終わりが今日以降）で、5週間後が次の年度に入るときは、その月まで延ばす
        $advanceMonth = Carbon::parse($advanceUntil)->startOfMonth();
        if ($lastMonth->copy()->endOfMonth()->gte(Carbon::today()) && $advanceMonth->gt($lastMonth)) {
            $lastMonth = $advanceMonth;
        }

        $months = [];
        for ($month = $firstMonth->copy(); $month->lte($lastMonth); $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        $from = $firstMonth->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $until = $lastMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $weekLessonNos = $this->weekLessonNos($from, $until);

        return [
            // 読み込んだ月（"2026-04" の形）
            'months' => $months,
            // その範囲の選べる授業日（古い順）
            'lessonDates' => array_values($this->selectableLessons($advanceUntil)
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $until)
                ->orderBy('date')
                ->pluck('date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->unique()
                ->all()),
            'weekLessonNos' => $weekLessonNos,
            // 授業がある週は休校の週にしない（授業実施単位ごとの休校日だけの週など）
            'holidayWeeks' => array_diff_key($this->holidayWeeks($from, $until), $weekLessonNos),
        ];
    }

    /**
     * 週ごとの、授業計画上の第何回か。キーはその週の月曜日の日付（例：2026-10-19 => 23）。
     * 日付選択のモーダルで、カレンダーの各週の行に第何回かを出すために使う。
     * 休校は週単位で決まるため、同じ週の授業回はふつう同じ回数になる。万一ずれていた場合は小さい方を出す。
     *
     * @return array<string, int>
     */
    private function weekLessonNos(string $from, string $until): array
    {
        return Lesson::with('lessonPlan:id,no')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $until)
            ->get(['id', 'date', 'lesson_plan_id'])
            ->groupBy(fn (Lesson $lesson) => $lesson->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(fn (Collection $weekLessons) => (int) $weekLessons->min(fn (Lesson $lesson) => $lesson->lessonPlan->no))
            ->all();
    }

    /**
     * 休校の週と、その理由。キーはその週の月曜日の日付（例：2026-11-02 => 文化の日）。
     * 日付選択のモーダルで、カレンダーの休校の週を枠で囲んで理由を出すために使う。
     * 全体の休校だけを対象にする。1つの週に別の理由の休校日があれば「・」でつなぐ。
     *
     * @return array<string, string>
     */
    private function holidayWeeks(string $from, string $until): array
    {
        return Holiday::whereNull('section_id')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $until)
            ->orderBy('date')
            ->get()
            ->groupBy(fn (Holiday $holiday) => $holiday->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(fn (Collection $weekHolidays) => $weekHolidays->pluck('reason')->unique()->join('・'))
            ->all();
    }

    /**
     * 今後の休校（全体の休校だけ）を、期間ごとにまとめたもの（近い順に最大3件）。
     * 日付選択のモーダルで、次に休みになる週を確かめるために使う。
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
            // 補講先の回を表すキー。同じ生徒のほかの欠席分の補講がすでに入っている回かを、画面側で判定するのに使う
            'sessionKey' => $this->sessionKey($candidate),
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
     * @param  Collection<array-key, \Illuminate\Database\Eloquent\Collection<int, Attendance>>  $studentMakeups  その日の生徒の、補講予定が入っている欠席記録（キーは生徒ID）
     * @return array<string, mixed>
     */
    private function buildBlock(Collection $sectionLessons, Collection $students, Collection $attendances, Collection $makeupAttendances, Collection $makeupCandidates, Collection $studentMakeups): array
    {
        $section = $sectionLessons->firstOrFail()->section;

        // 全ブロックの「来る側」の記録から、補講先がこのブロックの授業回のどれかになっているものだけを取り出す
        $blockMakeups = $makeupAttendances
            ->whereIn('makeup_lesson_id', $sectionLessons->pluck('id'))
            ->sortBy([
                fn (Attendance $a, Attendance $b) => $a->student->schoolClass->code <=> $b->student->schoolClass->code,
                fn (Attendance $a, Attendance $b) => $a->student->gradeOrder() <=> $b->student->gradeOrder(),
            ]);

        // 同じ生徒の補講（1時間前補講・振替＋30分前補講）は1つにまとめるので、生徒ごとに分ける
        $makeupsByStudent = $blockMakeups->groupBy('student_id');

        // このブロックに所属する生徒の行
        $rows = $students
            ->where('section_id', $section->id)
            ->sortBy([
                fn (Student $a, Student $b) => $a->schoolClass->code <=> $b->schoolClass->code,
                fn (Student $a, Student $b) => $a->gradeOrder() <=> $b->gradeOrder(),
            ])
            ->map(fn (Student $student) => $this->buildRow($student, $sectionLessons, $attendances, $makeupsByStudent, $studentMakeups))
            ->filter()
            ->values();

        // ほかの回から補講で来る生徒の行（1人1行）。
        // 自分のクラスの回に30分前補講で来る生徒は、所属する生徒の行にまとめる（catchUp）ので、ここには出さない
        $rowStudentIds = $rows->pluck('studentId');
        $makeupRows = $makeupsByStudent
            ->reject(fn (Collection $records, $studentId) => $rowStudentIds->contains($studentId))
            ->map(fn (Collection $records) => $this->buildMakeupRow($records))
            ->values();

        return [
            'sectionId' => $section->id,
            'title' => $this->blockTitle($sectionLessons),
            'startTime' => substr($section->start_time, 0, 5),
            'endTime' => substr($section->end_time, 0, 5),
            'rows' => $rows,
            'makeupRows' => $makeupRows,
            // このブロックの生徒が欠席したときの補講先の候補
            'makeupCandidates' => $makeupCandidates->get($section->id, collect()),
            // 所属する生徒の出欠。30分前補講もある生徒は、補講の出欠がまだなら未記録に数える（記録し忘れを残さないため）
            'summary' => $this->summarize($rows->map(
                fn (array $row) => $row['catchUp'] !== null && $row['catchUp']['makeupStatus'] === null ? null : $row['status']
            )),
            // ほかの回から補講で来る生徒の、補講先での出欠
            'makeupSummary' => $this->summarize($makeupRows->map(fn (array $row) => $row['makeupStatus'])),
        ];
    }

    /**
     * 生徒1人分の行のデータを作る。
     * このブロックの授業回の中から、その生徒の授業クラスの授業回を探し、その授業回の出席記録から表示ステータスを決める。
     * その日に自分のクラスの授業回が無い生徒はnullを返す（行に出さない）。
     *
     * @param  Collection<int, Lesson>  $sectionLessons
     * @param  Collection<string, Attendance>  $attendances
     * @param  Collection<int, Collection<int, Attendance>>  $makeupsByStudent  このブロックに補講で来る欠席記録（キーは生徒ID）
     * @param  Collection<array-key, \Illuminate\Database\Eloquent\Collection<int, Attendance>>  $studentMakeups  その日の生徒の、補講予定が入っている欠席記録（キーは生徒ID）
     * @return array<string, mixed>|null
     */
    private function buildRow(Student $student, Collection $sectionLessons, Collection $attendances, Collection $makeupsByStudent, Collection $studentMakeups): ?array
    {
        $lesson = $sectionLessons->first(
            fn (Lesson $lesson) => $lesson->lessonPlan->school_class_id === $student->school_class_id
        );

        if ($lesson === null) {
            return null;
        }

        $attendance = $attendances->get("{$student->id}-{$lesson->id}");

        // 自分のクラスのこの回に、別の日の欠席分の補講（30分前補講・1時間前補講など）でも来るときの、その欠席記録
        $catchUp = $makeupsByStudent->get($student->id);

        // この生徒のほかの欠席分の補講予定（この回の欠席の記録は除く）。補講先の回のキーと補講予定
        $otherMakeups = collect();
        foreach ($studentMakeups->get($student->id) ?? [] as $record) {
            $recordMakeupLesson = $record->makeupLesson;

            if ($record->lesson_id !== $lesson->id && $recordMakeupLesson !== null) {
                $otherMakeups->push([
                    'sessionKey' => $this->sessionKey($recordMakeupLesson),
                    'makeupType' => $record->makeup_type,
                    // 何日の欠席分か（補講予定を変えるときの確認で、残る記録を示すのに使う）
                    'absentDate' => $record->lesson->date->toDateString(),
                ]);
            }
        }

        // この回の欠席の補講先に、同じ生徒が入れている補講予定（この欠席の分も含む）。1時間前補講かを判定するのに使う
        $makeupGroupTypes = [];
        $makeupSessionKey = null;
        $ownMakeupLesson = $attendance?->makeupLesson;
        if ($attendance !== null && $ownMakeupLesson !== null) {
            $makeupSessionKey = $this->sessionKey($ownMakeupLesson);
            $makeupGroupTypes = $otherMakeups
                ->where('sessionKey', $makeupSessionKey)
                ->pluck('makeupType')
                ->push($attendance->makeup_type)
                ->values()
                ->all();
        }

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
            // 補講先の授業回のID（同じ補講先を選び直したかを、画面側で判定するのに使う）
            'makeupLessonId' => $attendance?->makeup_lesson_id,
            'makeupStatus' => $attendance?->makeup_status,
            // 補講先の回を表すキー（同じ回を選び直したかを、画面側で判定するのに使う）
            'makeupSessionKey' => $makeupSessionKey,
            // 補講先の回に、同じ生徒が入れている補講予定（この欠席の分も含む。30分前補講が2つなら1時間前補講）
            'makeupGroupTypes' => $makeupGroupTypes,
            // ほかの欠席分の補講予定（補講先を選ぶモーダルで、同じ回にすでに入っている補講を示すのに使う）
            'otherMakeups' => $otherMakeups,
            // 記録したスタッフの名前（スタッフがまだ対応していなければnull）
            'recordedBy' => $attendance?->staff?->name,
            // 保護者からの事前連絡で、スタッフがまだ対応していない記録か
            'isNotice' => $attendance !== null && $attendance->staff_id === null,
            // 保護者からの連絡事項（欠席・遅刻・補講登録画面の自由記入欄）
            'note' => $attendance?->note,
            // 自分のクラスのこの回に補講でも来る場合の、補講の情報（行を分けず、この行で補講の出欠も記録する）
            'catchUp' => $catchUp === null ? null : $this->makeupGroup($catchUp),
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
     * 同じ生徒の補講が2つ（1時間前補講・振替＋30分前補講）あれば、1行にまとめる。
     *
     * @param  Collection<int, Attendance>  $records  この生徒がこのブロックに補講で来る欠席記録
     * @return array<string, mixed>
     */
    private function buildMakeupRow(Collection $records): array
    {
        $first = $records->firstOrFail();

        return [
            'kind' => 'makeup',
            'studentId' => $first->student_id,
            'name' => $first->student->name,
            'nameKana' => $first->student->name_kana,
            'schoolClassName' => $first->student->schoolClass->display_name,
            'lessonId' => $first->makeup_lesson_id,
            ...$this->makeupGroup($records),
        ];
    }

    /**
     * 同じ生徒が同じ回に来る補講（1つ、または1時間前補講・振替＋30分前補講のように2つ）の情報。
     *
     * @param  Collection<int, Attendance>  $records
     * @return array<string, mixed>
     */
    private function makeupGroup(Collection $records): array
    {
        // 休んだ日の古い順
        $records = $records->sortBy(fn (Attendance $record) => $record->lesson->date->toDateString())->values();
        $first = $records->firstOrFail();

        return [
            // 補講先での出欠を記録するとき（updateMakeupルート）に使う、元の欠席記録のID（同じ回の補講はまとめて記録される）
            'attendanceId' => $first->id,
            // 補講予定（振替を先にする。例：振替・30分前補講、30分前補講・30分前補講）
            'makeupTypes' => array_values($records->pluck('makeup_type')
                ->sortBy(fn ($type) => $type === '振替' ? 0 : 1)
                ->values()
                ->all()),
            // 何日の欠席分の補講か（例：2026-09-24。画面では「9/24欠席分」と表示する）と、その日の保護者からの連絡事項
            'absences' => array_values($records->map(fn (Attendance $record) => [
                'absentDate' => $record->lesson->date->toDateString(),
                'note' => $record->note,
            ])->all()),
            // 補講先での出欠。まとめて記録するので、ふつうはどれも同じ。1つでも未記録なら未記録にする
            'makeupStatus' => $records->contains(fn (Attendance $record) => $record->makeup_status === null) ? null : $first->makeup_status,
        ];
    }

    /**
     * 行ごとの出欠（出席・欠席・未記録ならnull）を、「出席・欠席・未記録」に分けて数える。
     *
     * @param  Collection<int, mixed>  $statuses
     * @return array{present: int, absent: int, unrecorded: int}
     */
    private function summarize(Collection $statuses): array
    {
        return [
            'present' => $statuses->filter(fn ($status) => $status === '出席')->count(),
            'absent' => $statuses->filter(fn ($status) => $status === '欠席')->count(),
            'unrecorded' => $statuses->filter(fn ($status) => $status === null)->count(),
        ];
    }
}
