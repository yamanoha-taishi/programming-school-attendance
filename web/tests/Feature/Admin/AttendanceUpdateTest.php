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
 * 出席記録・一覧画面で、通常の行の出欠（出席、または欠席と補講予定）を記録する（admin.attendances.update）テスト。
 * 仕様はルーティング設計のupdateの備考を参照。
 */
class AttendanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // lessonsの外部キー（lesson_plan_id・section_id）に必要なマスタだけを登録する
        $this->seed([SectionSeeder::class, SchoolClassSeeder::class, LessonPlanSeeder::class]);

        $this->staff = Staff::factory()->create();
    }

    public function test_present_is_recorded_when_there_is_no_record_yet()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1');

        $response = $this->patchAttendance($lesson, $student, ['status' => '出席']);

        // 一覧画面から送るので、一覧画面に戻る
        $response->assertRedirect(route('admin.attendances.index', ['date' => '2026-10-03']));
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '出席',
            'is_late' => false,
            'staff_id' => $this->staff->id,
        ]);
    }

    public function test_late_notice_is_kept_when_present_is_recorded()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1');

        // 保護者から遅刻の連絡だけが来ている（スタッフ未対応なのでstaff_idはNULL）
        Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => null,
            'is_late' => true,
        ]);

        $this->patchAttendance($lesson, $student, ['status' => '出席']);

        // 新しく作らずに既存のレコードを更新し、遅刻フラグは遅刻の連絡があった記録として残す（表示は「出席」になる）
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '出席',
            'is_late' => true,
            'staff_id' => $this->staff->id,
        ]);
    }

    public function test_makeup_plan_is_cleared_when_absent_is_changed_to_present()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $makeupLesson = $this->createLesson('2026-10-10', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1');

        // 欠席・振替予定で記録されていたが、実際には来た
        Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => '振替',
            'makeup_lesson_id' => $makeupLesson->id,
        ]);

        $this->patchAttendance($lesson, $student, ['status' => '出席']);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '出席',
            'makeup_type' => null,
            'makeup_lesson_id' => null,
            'makeup_status' => null,
        ]);
    }

    public function test_student_of_another_section_is_rejected()
    {
        // 土曜A1の授業回に、水曜Bの生徒の出席を記録しようとする
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('B', 'B1', '水');

        $response = $this->patchAttendance($lesson, $student, ['status' => '出席']);

        $response->assertNotFound();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_student_of_another_class_in_the_same_section_is_rejected()
    {
        // 土曜BのB1クラスの授業回に、同じ土曜BのB2クラスの生徒の出席を記録しようとする
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B2');

        $response = $this->patchAttendance($lesson, $student, ['status' => '出席']);

        $response->assertNotFound();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_invalid_status_is_rejected()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1');

        $response = $this->patchAttendance($lesson, $student, ['status' => '遅刻']);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_guests_cannot_record_attendance()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1');

        $response = $this->patch(
            route('admin.attendances.update', ['lesson' => $lesson, 'student' => $student]),
            ['status' => '出席'],
        );

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_present_on_a_future_lesson_is_rejected()
    {
        // 今日は10/1。10/3の授業回はまだ来ていないので、出席は記録できない
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1');

        $response = $this->patchAttendance($lesson, $student, ['status' => '出席']);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_absent_on_a_future_lesson_can_be_recorded_in_advance()
    {
        // 今日は10/1。電話で連絡を受けて、10/3の欠席を前もって登録する
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1');

        $response = $this->patchAttendance($lesson, $student, ['status' => '欠席', 'makeup_type' => '未定']);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
        ]);
    }

    // ------------------------------------------------------------
    // 欠席と補講予定
    // ------------------------------------------------------------

    public function test_absent_with_undecided_makeup_is_recorded()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        // 未定のときは補講先を使わないので、送られてきても保存しない
        $otherLesson = $this->createLesson('2026-10-10', 'B', 'B1');
        $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '未定',
            'makeup_lesson_id' => $otherLesson->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
            'makeup_type' => '未定',
            'makeup_lesson_id' => null,
            'staff_id' => $this->staff->id,
        ]);
    }

    public function test_absent_with_no_makeup_is_recorded()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        $this->patchAttendance($lesson, $student, ['status' => '欠席', 'makeup_type' => '補講なし']);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
            'makeup_type' => '補講なし',
            'makeup_lesson_id' => null,
        ]);
    }

    public function test_absent_with_transfer_to_another_section_is_recorded()
    {
        // 土曜B（B1）を休んで、同じB1クラスの水曜Bの回に振替で行く
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $makeupLesson = $this->createLesson('2026-10-07', 'B', 'B1', '水');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => $makeupLesson->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => $makeupLesson->id,
        ]);
    }

    public function test_absent_with_catch_up_in_another_section_is_recorded()
    {
        // 特別な調整：土曜の生徒が、同じクラスの木曜（ここでは水曜B）の回で30分前補講を受ける。曜日は問わない
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $makeupLesson = $this->createLesson('2026-10-14', 'B', 'B1', '水');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '30分前補講',
            'makeup_lesson_id' => $makeupLesson->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'makeup_type' => '30分前補講',
            'makeup_lesson_id' => $makeupLesson->id,
        ]);
    }

    public function test_makeup_status_is_cleared_when_makeup_plan_is_changed()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $oldMakeupLesson = $this->createLesson('2026-10-07', 'B', 'B1', '水');
        $newMakeupLesson = $this->createLesson('2026-10-10', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        // 前の補講先で「出席」まで記録されていた
        Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => '振替',
            'makeup_lesson_id' => $oldMakeupLesson->id,
            'makeup_status' => '出席',
        ]);

        $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '30分前補講',
            'makeup_lesson_id' => $newMakeupLesson->id,
        ]);

        // 補講予定を決め直したので、補講先での出欠は未記録に戻る（「振替済み」のまま残らない）
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'makeup_type' => '30分前補講',
            'makeup_lesson_id' => $newMakeupLesson->id,
            'makeup_status' => null,
        ]);
    }

    public function test_makeup_fields_are_ignored_when_present_is_recorded()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $otherLesson = $this->createLesson('2026-10-10', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        $this->patchAttendance($lesson, $student, [
            'status' => '出席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => $otherLesson->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => '出席',
            'makeup_type' => null,
            'makeup_lesson_id' => null,
        ]);
    }

    public function test_absent_without_makeup_type_is_rejected()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, ['status' => '欠席']);

        $response->assertSessionHasErrors('makeup_type');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_invalid_makeup_type_is_rejected()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, ['status' => '欠席', 'makeup_type' => '補講']);

        $response->assertSessionHasErrors('makeup_type');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_transfer_without_makeup_lesson_is_rejected()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, ['status' => '欠席', 'makeup_type' => '振替']);

        $response->assertSessionHasErrors('makeup_lesson_id');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_nonexistent_makeup_lesson_is_rejected()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => 99999,
        ]);

        $response->assertSessionHasErrors('makeup_lesson_id');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_basic_makeup_lesson_of_another_class_is_rejected()
    {
        // 基礎クラス1組（A1）の生徒の補講先に、基礎クラス2組（A2）の授業回は選べない
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $otherClassLesson = $this->createLesson('2026-10-10', 'A2', 'A2');
        $student = $this->createStudent('A1', 'A1');

        $response = $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => $otherClassLesson->id,
        ]);

        $response->assertSessionHasErrors('makeup_lesson_id');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_advanced_makeup_lesson_of_another_class_is_recorded()
    {
        // 応用クラスは、どの組の回でも補講を受けられる。B1クラスの生徒が、B3クラスの授業回しかない水曜Bの回に振替で行く
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $otherClassLesson = $this->createLesson('2026-10-07', 'B', 'B3', '水');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => $otherClassLesson->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'makeup_lesson_id' => $otherClassLesson->id,
        ]);
    }

    public function test_advanced_makeup_lesson_in_the_same_session_is_rejected()
    {
        // 休んだ回と同じ日・同じ時間に一緒に授業をしているB2クラスの授業回は、補講先に選べない
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $sameSessionLesson = $this->createLesson('2026-10-03', 'B', 'B2');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => $sameSessionLesson->id,
        ]);

        $response->assertSessionHasErrors('makeup_lesson_id');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_absent_lesson_itself_as_makeup_lesson_is_rejected()
    {
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1');

        $response = $this->patchAttendance($lesson, $student, [
            'status' => '欠席',
            'makeup_type' => '30分前補講',
            'makeup_lesson_id' => $lesson->id,
        ]);

        $response->assertSessionHasErrors('makeup_lesson_id');
        $this->assertDatabaseCount('attendances', 0);
    }

    /**
     * スタッフとして、出席記録・一覧画面（指定した授業回の日付）から出欠を送信する。
     * from()で送信元の画面を指定しておくと、back()でその画面に戻ることを確認できる。
     *
     * @param  array<string, mixed>  $data
     */
    private function patchAttendance(Lesson $lesson, Student $student, array $data): TestResponse
    {
        return $this->actingAs($this->staff, 'staff')
            ->from(route('admin.attendances.index', ['date' => $lesson->date->toDateString()]))
            ->patch(route('admin.attendances.update', ['lesson' => $lesson, 'student' => $student]), $data);
    }

    /**
     * 指定した曜日（省略時は土曜日）の授業実施単位に、指定した授業クラスの授業回を1件作る。
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
     * 指定した曜日（省略時は土曜日）の授業実施単位・授業クラスに所属する生徒を1人作る。
     */
    private function createStudent(string $sectionName, string $classCode, string $weekday = '土'): Student
    {
        return Student::create([
            'guardian_id' => Guardian::factory()->create()->id,
            'school_class_id' => SchoolClass::where('code', $classCode)->firstOrFail()->id,
            'section_id' => Section::where('name', $sectionName)->where('weekday', $weekday)->firstOrFail()->id,
            'grade' => '小3',
            'name' => 'テスト生徒',
            'name_kana' => 'テストセイト',
        ]);
    }
}
