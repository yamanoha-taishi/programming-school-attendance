<?php

namespace App\Http\Controllers\Admin;

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

        $blocks = Lesson::with(['section', 'lessonPlan.schoolClass'])
            ->whereDate('date', $date)
            ->get()
            ->sortBy(fn (Lesson $lesson) => $lesson->section->start_time)
            ->groupBy('section_id')
            ->map(fn (Collection $lessons) => $this->buildBlock($lessons))
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
     * @param  Collection<int, Lesson>  $lessons
     * @return array<string, mixed>
     */
    private function buildBlock(Collection $lessons): array
    {
        $section = $lessons->firstOrFail()->section;

        return [
            'sectionId' => $section->id,
            'title' => $this->blockTitle($lessons),
            'startTime' => substr($section->start_time, 0, 5),
            'endTime' => substr($section->end_time, 0, 5),
            'rows' => [],
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
