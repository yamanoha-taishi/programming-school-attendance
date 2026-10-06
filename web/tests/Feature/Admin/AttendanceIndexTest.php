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
 * 表示する日付の決め方と、授業実施単位ごとのブロック・生徒の行の組み立て、出欠サマリーを確認する。
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
            ->where('blocks.0.rows.0.nameKana', 'ア出席')
            ->where('blocks.0.rows.0.lessonId', $lesson->id)
            ->where('blocks.0.rows.0.displayStatus', '通常出席')
            ->where('blocks.0.rows.1.displayStatus', '未定')
        );
    }

    // ------------------------------------------------------------
    // 振替・30分前補講で「来る側」の行
    // ------------------------------------------------------------

    public function test_makeup_row_is_shown_in_the_block_of_the_makeup_lesson()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');

        // 水曜Bの生徒が、9/30（水）を休んで10/3（土）の土曜Bに振替で来る
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来る', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $makeup = $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 1)
            ->where('blocks.0.rows.0.kind', 'makeup')
            ->where('blocks.0.rows.0.attendanceId', $makeup->id)
            ->where('blocks.0.rows.0.studentId', $student->id)
            ->where('blocks.0.rows.0.name', 'ア振替で来る')
            ->where('blocks.0.rows.0.nameKana', 'ア振替で来る')
            ->where('blocks.0.rows.0.schoolClassName', '応用クラス1組')
            ->where('blocks.0.rows.0.lessonId', $makeupLesson->id)
            ->where('blocks.0.rows.0.makeupType', '振替')
            ->where('blocks.0.rows.0.absentDate', '2026-09-30')
            ->where('blocks.0.rows.0.makeupStatus', null)
        );
    }

    public function test_makeup_rows_are_placed_after_regular_rows()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createStudent('B', 'B1', '小6', 'イ通常の生徒');

        // 授業クラス・学年・名前のどれで比べても通常の生徒より前に来る生徒でも、「来る側」は末尾に並ぶ
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来る', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 2)
            ->where('blocks.0.rows.0.kind', 'regular')
            ->where('blocks.0.rows.0.name', 'イ通常の生徒')
            ->where('blocks.0.rows.1.kind', 'makeup')
            ->where('blocks.0.rows.1.name', 'ア振替で来る')
        );
    }

    public function test_student_catching_up_in_own_class_has_both_regular_and_makeup_rows()
    {
        // 9/26（土）を休み、自分のクラスの次の回（10/3）に30分前補講で来る
        $absentLesson = $this->createLesson('2026-09-26', 'A1', 'A1');
        $makeupLesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1', '年中', 'ア自分のクラスで補講');
        $this->createStudent('A1', 'A1', '年長', 'イ補講なしの生徒');
        $makeup = $this->createMakeup($student, $absentLesson, $makeupLesson, '30分前補講');

        $response = $this->getAttendances('2026-10-03');

        // 通常の行（「補講あり」の印付き）と、末尾の補講の行の2行になる
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 3)
            ->where('blocks.0.rows.0.kind', 'regular')
            ->where('blocks.0.rows.0.studentId', $student->id)
            ->where('blocks.0.rows.0.hasMakeup', true)
            ->where('blocks.0.rows.1.kind', 'regular')
            ->where('blocks.0.rows.1.hasMakeup', false)
            ->where('blocks.0.rows.2.kind', 'makeup')
            ->where('blocks.0.rows.2.studentId', $student->id)
            ->where('blocks.0.rows.2.attendanceId', $makeup->id)
            ->where('blocks.0.rows.2.makeupType', '30分前補講')
            ->where('blocks.0.rows.2.absentDate', '2026-09-26')
        );
    }

    public function test_makeup_row_is_not_shown_in_other_blocks()
    {
        // 10/3に土曜A1（10:00）と土曜B（14:30）の2ブロックがあり、補講先は土曜Bだけ
        $this->createLesson('2026-10-03', 'A1', 'A1');
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来る', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 2)
            ->where('blocks.0.title', '基礎クラス1組')
            ->has('blocks.0.rows', 0)
            ->has('blocks.1.rows', 1)
            ->where('blocks.1.rows.0.kind', 'makeup')
        );
    }

    public function test_makeup_status_is_shown_on_makeup_row()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来た', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替', '出席');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.rows.0.makeupStatus', '出席')
        );
    }

    public function test_makeup_row_of_soft_deleted_student_is_not_shown()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1', '小3', 'ア削除された生徒', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');
        $student->delete();

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page->has('blocks.0.rows', 0));
    }

    // ------------------------------------------------------------
    // 出欠サマリー
    // ------------------------------------------------------------

    public function test_regular_rows_are_counted_by_status()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');

        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'ア出席'), $lesson, '出席');
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'イ遅刻して出席'), $lesson, '出席', isLate: true);
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'ウ欠席'), $lesson, '欠席');
        $this->createStudent('A1', 'A1', '年中', 'エ記録なし');
        // 遅刻の連絡だけ来ていて、まだ来たかどうか記録していない
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'オ遅刻連絡のみ'), $lesson, null, isLate: true);

        // 振替済みの生徒も、この日の記録としては「欠席」に数える
        $student = $this->createStudent('A1', 'A1', '年中', 'カ振替済み');
        $makeupLesson = $this->createLesson('2026-10-07', 'B', 'B1', '水');
        $this->createMakeup($student, $lesson, $makeupLesson, '振替', '出席');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.rows.0.status', '出席')
            ->where('blocks.0.rows.3.status', null)
            ->where('blocks.0.summary', ['present' => 2, 'absent' => 2, 'unrecorded' => 2])
        );
    }

    public function test_makeup_rows_are_counted_by_makeup_status()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');

        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'ア補講に出席', weekday: '水'), $absentLesson, $makeupLesson, '振替', '出席');
        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'イ補講も欠席', weekday: '水'), $absentLesson, $makeupLesson, '振替', '欠席');
        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'ウ補講は未記録', weekday: '水'), $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 3)
            ->where('blocks.0.summary', ['present' => 1, 'absent' => 1, 'unrecorded' => 1])
        );
    }

    public function test_student_catching_up_in_own_class_is_counted_separately_for_each_row()
    {
        // 自分のクラスの回に30分前補講で来る生徒。通常の授業は出席を記録済み、補講はまだ記録していない
        $absentLesson = $this->createLesson('2026-09-26', 'A1', 'A1');
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1', '年中', 'ア自分のクラスで補講');
        $this->createAttendance($student, $lesson, '出席');
        $this->createMakeup($student, $absentLesson, $lesson, '30分前補講');

        $response = $this->getAttendances('2026-10-03');

        // 1人の生徒でも、通常の行と補講の行を別々に数える（補講の記録忘れが「未記録」に残る）
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 2)
            ->where('blocks.0.summary', ['present' => 1, 'absent' => 0, 'unrecorded' => 1])
        );
    }

    public function test_day_summary_is_total_of_all_blocks()
    {
        // 土曜A1：出席1・未記録1
        $lessonA1 = $this->createLesson('2026-10-03', 'A1', 'A1');
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'ア出席'), $lessonA1, '出席');
        $this->createStudent('A1', 'A1', '年中', 'イ記録なし');

        // 土曜B：欠席1・「来る側」の出席1
        $lessonB = $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createAttendance($this->createStudent('B', 'B1', '小3', 'ウ欠席'), $lessonB, '欠席');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'エ振替で来た', weekday: '水'), $absentLesson, $lessonB, '振替', '出席');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.summary', ['present' => 1, 'absent' => 0, 'unrecorded' => 1])
            ->where('blocks.1.summary', ['present' => 1, 'absent' => 1, 'unrecorded' => 0])
            ->where('daySummary', ['present' => 2, 'absent' => 1, 'unrecorded' => 1])
        );
    }

    public function test_day_summary_is_zero_on_a_day_without_lessons()
    {
        $response = $this->getAttendances('2026-10-04');

        $response->assertInertia(fn ($page) => $page
            ->where('daySummary', ['present' => 0, 'absent' => 0, 'unrecorded' => 0])
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
     * 指定した曜日（省略時は土曜日）の授業実施単位（A1／A2／A3／B）に、指定した授業クラスの授業回を1件作る。
     * lessonsの(lesson_plan_id, section_id)の複合UNIQUE制約に当たらないよう、
     * その授業実施単位でまだ使っていない授業内容を選ぶ。
     */
    private function createLesson(string $date, string $sectionName, string $classCode, string $weekday = '土'): Lesson
    {
        $section = Section::where('name', $sectionName)->where('weekday', $weekday)->firstOrFail();

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
     * 指定した曜日（省略時は土曜日）の授業実施単位に所属する生徒を1人作る。
     * 並び順の確認で分かりやすいよう、名前と名前カナには同じ文字列を入れる。
     */
    private function createStudent(
        string $sectionName,
        string $classCode,
        string $grade,
        string $name,
        ?string $leaveFrom = null,
        ?string $leaveUntil = null,
        string $weekday = '土',
    ): Student {
        return Student::create([
            'guardian_id' => Guardian::factory()->create()->id,
            'school_class_id' => SchoolClass::where('code', $classCode)->firstOrFail()->id,
            'section_id' => Section::where('name', $sectionName)->where('weekday', $weekday)->firstOrFail()->id,
            'grade' => $grade,
            'name' => $name,
            'name_kana' => $name,
            'leave_from' => $leaveFrom,
            'leave_until' => $leaveUntil,
        ]);
    }

    /**
     * 欠席して、別の授業回に振替・30分前補講で来る予定の欠席記録を作る。
     */
    private function createMakeup(
        Student $student,
        Lesson $absentLesson,
        Lesson $makeupLesson,
        string $makeupType,
        ?string $makeupStatus = null,
    ): Attendance {
        return Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $absentLesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => $makeupType,
            'makeup_lesson_id' => $makeupLesson->id,
            'makeup_status' => $makeupStatus,
        ]);
    }

    /**
     * 通常の授業回の出席記録を作る。statusがnullなら未記録（遅刻の連絡だけ来ている状態など）。
     */
    private function createAttendance(Student $student, Lesson $lesson, ?string $status, bool $isLate = false): Attendance
    {
        return Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => $status,
            'is_late' => $isLate,
        ]);
    }
}
