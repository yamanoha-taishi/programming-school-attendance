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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 出席記録・一覧画面で、振替・30分前補講で「来る側」の行の、補講先での出欠を記録する
 * （admin.attendances.makeup）テスト。仕様はルーティング設計のupdateMakeupの備考を参照。
 */
class AttendanceUpdateMakeupTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    private Lesson $absentLesson;

    private Lesson $makeupLesson;

    protected function setUp(): void
    {
        parent::setUp();

        // lessonsの外部キー（lesson_plan_id・section_id）に必要なマスタだけを登録する
        $this->seed([SectionSeeder::class, SchoolClassSeeder::class, LessonPlanSeeder::class]);

        $this->staff = Staff::factory()->create();

        // 9/30（水）の水曜Bを休んで、10/3（土）の土曜Bに振替で来る、という場面を共通で使う
        $this->absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
    }

    public function test_makeup_present_is_recorded()
    {
        // 欠席を記録したのは別のスタッフ
        $recordedBy = Staff::factory()->create();
        $attendance = $this->createAbsence('振替', $this->makeupLesson, $recordedBy);

        $response = $this->patchMakeup($attendance, ['makeup_status' => '出席']);

        // 一覧画面（補講先の授業回の日付）に戻る
        $response->assertRedirect(route('admin.attendances.index', ['date' => '2026-10-03']));

        // 更新するのはmakeup_statusだけ。欠席の記録そのもの（status・staff_idなど）は変えない
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => '欠席',
            'makeup_type' => '振替',
            'makeup_lesson_id' => $this->makeupLesson->id,
            'makeup_status' => '出席',
            'staff_id' => $recordedBy->id,
        ]);
    }

    public function test_makeup_absent_is_recorded()
    {
        $attendance = $this->createAbsence('30分前補講', $this->makeupLesson);

        $this->patchMakeup($attendance, ['makeup_status' => '欠席']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'makeup_status' => '欠席',
        ]);
    }

    public function test_makeup_status_can_be_changed_after_recorded()
    {
        // 「欠席」で記録したが、遅れて来たので「出席」に直す
        $attendance = $this->createAbsence('振替', $this->makeupLesson);
        $attendance->update(['makeup_status' => '欠席']);

        $this->patchMakeup($attendance, ['makeup_status' => '出席']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'makeup_status' => '出席',
        ]);
    }

    public function test_record_with_undecided_makeup_type_is_rejected()
    {
        // 補講予定が「未定」の欠席には、補講先が無い
        $attendance = $this->createAbsence('未定', null);

        $response = $this->patchMakeup($attendance, ['makeup_status' => '出席']);

        $response->assertSessionHasErrors('makeup_status');
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'makeup_status' => null]);
    }

    public function test_record_with_no_makeup_is_rejected()
    {
        $attendance = $this->createAbsence('補講なし', null);

        $response = $this->patchMakeup($attendance, ['makeup_status' => '出席']);

        $response->assertSessionHasErrors('makeup_status');
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'makeup_status' => null]);
    }

    public function test_record_without_makeup_lesson_is_rejected()
    {
        // 振替の予定だが、振替先の授業回がまだ決まっていない
        $attendance = $this->createAbsence('振替', null);

        $response = $this->patchMakeup($attendance, ['makeup_status' => '出席']);

        $response->assertSessionHasErrors('makeup_status');
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'makeup_status' => null]);
    }

    public function test_invalid_makeup_status_is_rejected()
    {
        $attendance = $this->createAbsence('振替', $this->makeupLesson);

        $response = $this->patchMakeup($attendance, ['makeup_status' => '遅刻']);

        $response->assertSessionHasErrors('makeup_status');
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'makeup_status' => null]);
    }

    public function test_guests_cannot_record_makeup_attendance()
    {
        $attendance = $this->createAbsence('振替', $this->makeupLesson);

        $response = $this->patch(
            route('admin.attendances.makeup', ['attendance' => $attendance]),
            ['makeup_status' => '出席'],
        );

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'makeup_status' => null]);
    }

    /**
     * スタッフとして、出席記録・一覧画面（補講先の授業回の日付）から補講先の出欠を送信する。
     * from()で送信元の画面を指定しておくと、back()でその画面に戻ることを確認できる。
     *
     * @param  array<string, mixed>  $data
     */
    private function patchMakeup(Attendance $attendance, array $data): TestResponse
    {
        return $this->actingAs($this->staff, 'staff')
            ->from(route('admin.attendances.index', ['date' => '2026-10-03']))
            ->patch(route('admin.attendances.makeup', ['attendance' => $attendance]), $data);
    }

    /**
     * 水曜Bの生徒が9/30の授業回を欠席した記録を作る。
     */
    private function createAbsence(string $makeupType, ?Lesson $makeupLesson, ?Staff $recordedBy = null): Attendance
    {
        $student = Student::create([
            'guardian_id' => Guardian::factory()->create()->id,
            'school_class_id' => SchoolClass::where('code', 'B1')->firstOrFail()->id,
            'section_id' => $this->absentLesson->section_id,
            'grade' => '小3',
            'name' => 'テスト生徒',
            'name_kana' => 'テストセイト',
        ]);

        return Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $this->absentLesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => $makeupType,
            'makeup_lesson_id' => $makeupLesson?->id,
            'staff_id' => $recordedBy?->id,
        ]);
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
}
