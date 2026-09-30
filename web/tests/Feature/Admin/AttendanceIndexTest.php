<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Lesson;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use Database\Seeders\LessonPlanSeeder;
use Database\Seeders\SchoolClassSeeder;
use Database\Seeders\SectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 出席記録・一覧画面（admin.attendances.index）のテスト。
 * 表示する日付の決め方と、授業実施単位ごとのブロック・生徒の行の組み立てを確認する。
 * 仕様はルーティング設計のindexの備考、要件定義書5.4を参照。
 */
class AttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // lessonsの外部キー（lesson_plan_id・section_id）に必要なマスタだけを登録する
        $this->seed([SectionSeeder::class, SchoolClassSeeder::class, LessonPlanSeeder::class]);

        $this->staff = Staff::factory()->create();

        // 「今日」を2026-09-26（土）に固定する。いつテストを実行しても同じ結果になるようにするため。
        // travelTo()で動かした時刻は、テストの終了時にLaravelが自動で元に戻す。
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00'));
    }

    public function test_specified_date_is_used()
    {
        $this->createLessonOn('2026-09-19');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => '2026-09-19']));

        $response->assertInertia(fn ($page) => $page
            ->component('admin/attendances/index')
            ->where('date', '2026-09-19')
        );
    }

    public function test_specified_date_is_used_even_if_there_are_no_lessons_on_that_day()
    {
        // 授業のない日を指定した場合も、直近の授業日に置き換えずにその日付のまま表示する
        $this->createLessonOn('2026-09-19');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => '2026-09-20']));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-20'));
    }

    public function test_today_is_used_when_there_are_lessons_today()
    {
        $this->createLessonOn('2026-09-19');
        $this->createLessonOn('2026-09-26');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index'));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-26'));
    }

    public function test_latest_past_lesson_date_is_used_when_there_are_no_lessons_today()
    {
        // 今日（9/26）より後の授業日（9/30）は対象外。今日以前で最も新しい9/23になる
        $this->createLessonOn('2026-09-16');
        $this->createLessonOn('2026-09-23');
        $this->createLessonOn('2026-09-30');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index'));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-23'));
    }

    public function test_today_is_used_when_there_are_no_past_lessons()
    {
        // 授業日が今日より後にしか無い場合（年度開始前など）は、今日の日付のまま表示する
        $this->createLessonOn('2026-09-30');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index'));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-26'));
    }

    public function test_invalid_date_is_rejected()
    {
        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => '2026/09/26']));

        $response->assertSessionHasErrors('date');
    }

    // ------------------------------------------------------------
    // ブロック（授業実施単位ごとのまとまり）
    // ------------------------------------------------------------

    public function test_blocks_are_ordered_by_start_time()
    {
        // 開始時刻が遅い応用クラス（土曜B 14:30）を先に作り、作成順ではなく開始時刻順に並ぶことを確認する
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2');
        $this->createLesson('2026-10-03', 'A1', 'A1');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 2)
            ->where('blocks.0.title', '基礎クラス1組')
            ->where('blocks.0.startTime', '10:00')
            ->where('blocks.0.endTime', '11:00')
            ->where('blocks.1.startTime', '14:30')
            ->where('blocks.1.endTime', '16:00')
        );
    }

    public function test_block_title_drops_class_number_when_multiple_classes_share_a_section()
    {
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 1)
            ->where('blocks.0.title', '応用クラス')
        );
    }

    public function test_block_title_is_class_name_when_only_one_class_uses_the_section()
    {
        $this->createLesson('2026-10-03', 'B', 'B1');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.title', '応用クラス1組')
        );
    }

    public function test_no_blocks_on_a_day_without_lessons()
    {
        $response = $this->getAttendances('2026-10-04');

        $response->assertInertia(fn ($page) => $page->has('blocks', 0));
    }

    // ------------------------------------------------------------
    // 生徒の行
    // ------------------------------------------------------------

    public function test_students_are_ordered_by_class_then_grade_then_name_kana()
    {
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2');

        // 作成順・名前順だけでは期待する並びにならないよう、ばらばらの順で作る
        $this->createStudent('B', 'B2', '小4', 'アベ');
        $this->createStudent('B', 'B1', '小5', 'イトウ');
        $this->createStudent('B', 'B1', '小3', 'ウエダ');
        $this->createStudent('B', 'B1', '小3', 'アオキ');

        $response = $this->getAttendances('2026-10-03');

        // ①授業クラス順（B1→B2）→②学年順（小3→小5）→③名前カナ順（アオキ→ウエダ）
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 4)
            ->where('blocks.0.rows.0.name', 'アオキ')
            ->where('blocks.0.rows.1.name', 'ウエダ')
            ->where('blocks.0.rows.2.name', 'イトウ')
            ->where('blocks.0.rows.3.name', 'アベ')
            ->where('blocks.0.rows.3.schoolClassName', '応用クラス2組')
        );
    }

    public function test_students_on_leave_are_excluded()
    {
        $this->createLesson('2026-10-03', 'A1', 'A1');

        $this->createStudent('A1', 'A1', '年中', 'ア休会なし');
        $this->createStudent('A1', 'A1', '年中', 'イ休会前', '2026-11-01', null);
        $this->createStudent('A1', 'A1', '年中', 'ウ休会終了済み', '2026-09-01', '2026-09-30');
        $this->createStudent('A1', 'A1', '年中', 'エ休会中', '2026-09-01', '2026-10-31');
        $this->createStudent('A1', 'A1', '年中', 'オ休会中終了未定', '2026-09-01', null);
        // 休会開始日・終了日がちょうど当日の場合も休会中として扱う
        $this->createStudent('A1', 'A1', '年中', 'カ休会開始日が当日', '2026-10-03', null);
        $this->createStudent('A1', 'A1', '年中', 'キ休会終了日が当日', '2026-09-01', '2026-10-03');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 3)
            ->where('blocks.0.rows.0.name', 'ア休会なし')
            ->where('blocks.0.rows.1.name', 'イ休会前')
            ->where('blocks.0.rows.2.name', 'ウ休会終了済み')
        );
    }

    public function test_students_without_their_class_lesson_on_that_day_are_excluded()
    {
        // 土曜Bの授業回はB1クラスの分しかない日
        $this->createLesson('2026-10-03', 'B', 'B1');

        $this->createStudent('B', 'B1', '小3', 'アB1の生徒');
        // 同じ授業実施単位だが、この日に自分のクラス（B2）の授業回が無い
        $this->createStudent('B', 'B2', '小4', 'イB2の生徒');
        // 授業回が無い別の授業実施単位（土曜A2）の生徒
        $this->createStudent('A2', 'A2', '小1', 'ウA2の生徒');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 1)
            ->has('blocks.0.rows', 1)
            ->where('blocks.0.rows.0.name', 'アB1の生徒')
        );
    }

    public function test_row_has_lesson_id_and_display_status_from_attendance()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');

        $present = $this->createStudent('A1', 'A1', '年中', 'ア出席');
        $this->createStudent('A1', 'A1', '年中', 'イ記録なし');

        Attendance::create([
            'student_id' => $present->id,
            'lesson_id' => $lesson->id,
            'status' => '出席',
            'is_late' => false,
        ]);

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.rows.0.studentId', $present->id)
            ->where('blocks.0.rows.0.lessonId', $lesson->id)
            ->where('blocks.0.rows.0.displayStatus', '通常出席')
            ->where('blocks.0.rows.1.displayStatus', '未定')
        );
    }

    /**
     * 指定した日付の授業回を1件作る。
     * lessonsには(lesson_plan_id, section_id)の複合UNIQUE制約があるため、
     * まだ授業回を作っていない授業内容を毎回選んで重複しないようにしている。
     */
    private function createLessonOn(string $date): Lesson
    {
        $lessonPlan = LessonPlan::whereDoesntHave('lessons')->orderBy('id')->firstOrFail();

        return Lesson::create([
            'lesson_plan_id' => $lessonPlan->id,
            'section_id' => Section::orderBy('id')->firstOrFail()->id,
            'date' => $date,
        ]);
    }

    /**
     * スタッフとして、指定した日付の出席記録・一覧画面を開く。
     */
    private function getAttendances(string $date): TestResponse
    {
        return $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => $date]));
    }

    /**
     * 土曜日の授業実施単位（A1／A2／A3／B）に、指定した授業クラスの授業回を1件作る。
     * lessonsの(lesson_plan_id, section_id)の複合UNIQUE制約に当たらないよう、
     * その授業実施単位でまだ使っていない授業内容を選ぶ。
     */
    private function createLesson(string $date, string $sectionName, string $classCode): Lesson
    {
        $section = Section::where('name', $sectionName)->where('weekday', '土')->firstOrFail();

        $lessonPlan = LessonPlan::whereHas('schoolClass', fn ($query) => $query->where('code', $classCode))
            ->whereDoesntHave('lessons', fn ($query) => $query->where('section_id', $section->id))
            ->orderBy('no')
            ->firstOrFail();

        return Lesson::create([
            'lesson_plan_id' => $lessonPlan->id,
            'section_id' => $section->id,
            'date' => $date,
        ]);
    }

    /**
     * 土曜日の授業実施単位に所属する生徒を1人作る。
     * 並び順の確認で分かりやすいよう、名前と名前カナには同じ文字列を入れる。
     */
    private function createStudent(
        string $sectionName,
        string $classCode,
        string $grade,
        string $name,
        ?string $leaveFrom = null,
        ?string $leaveUntil = null,
    ): Student {
        return Student::create([
            'guardian_id' => Guardian::factory()->create()->id,
            'school_class_id' => SchoolClass::where('code', $classCode)->firstOrFail()->id,
            'section_id' => Section::where('name', $sectionName)->where('weekday', '土')->firstOrFail()->id,
            'grade' => $grade,
            'name' => $name,
            'name_kana' => $name,
            'leave_from' => $leaveFrom,
            'leave_until' => $leaveUntil,
        ]);
    }
}
