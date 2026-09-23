<?php

namespace Tests\Feature\Admin;

use App\Models\Guardian;
use App\Models\Lesson;
use App\Models\LessonPlan;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use Database\Seeders\LessonPlanSeeder;
use Database\Seeders\SchoolClassSeeder;
use Database\Seeders\SectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------
    // 未ログイン・保護者ログイン時は、/admin 配下に入れない
    // ------------------------------------------------------------

    public function test_guests_are_redirected_to_login_from_attendances()
    {
        $response = $this->get(route('admin.attendances.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login_from_menu()
    {
        $response = $this->get(route('admin.menu'));

        $response->assertRedirect(route('login'));
    }

    public function test_guardians_are_redirected_to_login_from_attendances()
    {
        $guardian = Guardian::factory()->create();

        $response = $this->actingAs($guardian, 'guardian')->get(route('admin.attendances.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guardians_are_redirected_to_login_from_menu()
    {
        $guardian = Guardian::factory()->create();

        $response = $this->actingAs($guardian, 'guardian')->get(route('admin.menu'));

        $response->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------
    // 出席記録・一覧画面：full_access / attendance_only どちらも使える
    // ------------------------------------------------------------

    public function test_attendance_only_staff_can_access_attendances()
    {
        $staff = Staff::factory()->create(['role' => 'attendance_only']);

        $response = $this->actingAs($staff, 'staff')->get(route('admin.attendances.index'));

        $response->assertOk();
    }

    public function test_full_access_staff_can_access_attendances()
    {
        $staff = Staff::factory()->create(['role' => 'full_access']);

        $response = $this->actingAs($staff, 'staff')->get(route('admin.attendances.index'));

        $response->assertOk();
    }

    public function test_attendance_only_staff_can_update_attendance()
    {
        $staff = Staff::factory()->create(['role' => 'attendance_only']);
        [$lesson, $student] = $this->createLessonAndStudent();

        $response = $this->actingAs($staff, 'staff')
            ->patch(route('admin.attendances.update', ['lesson' => $lesson, 'student' => $student]));

        $this->assertNotForbidden($response);
    }

    // ------------------------------------------------------------
    // 管理メニュー：full_access のみ
    // ------------------------------------------------------------

    public function test_attendance_only_staff_cannot_access_menu()
    {
        $staff = Staff::factory()->create(['role' => 'attendance_only']);

        $response = $this->actingAs($staff, 'staff')->get(route('admin.menu'));

        $response->assertForbidden();
    }

    public function test_full_access_staff_can_access_menu()
    {
        $staff = Staff::factory()->create(['role' => 'full_access']);

        $response = $this->actingAs($staff, 'staff')->get(route('admin.menu'));

        $response->assertOk();
    }

    // ------------------------------------------------------------
    // 保護者・生徒・スタッフの一覧／登録／編集／削除／パスワード再発行：full_access のみ
    // ------------------------------------------------------------

    /**
     * [HTTPメソッド, ルート名, ルートパラメータに使うモデルの種類（不要ならnull）]
     *
     * @return array<string, array{string, string, string|null}>
     */
    public static function fullAccessOnlyRoutes(): array
    {
        return [
            'guardians.index' => ['get', 'admin.guardians.index', null],
            'guardians.create' => ['get', 'admin.guardians.create', null],
            'guardians.store' => ['post', 'admin.guardians.store', null],
            'guardians.edit' => ['get', 'admin.guardians.edit', 'guardian'],
            'guardians.update' => ['patch', 'admin.guardians.update', 'guardian'],
            'guardians.destroy' => ['delete', 'admin.guardians.destroy', 'guardian'],
            'guardians.reissue-password' => ['post', 'admin.guardians.reissue-password', 'guardian'],

            'students.index' => ['get', 'admin.students.index', null],
            'students.create' => ['get', 'admin.students.create', null],
            'students.store' => ['post', 'admin.students.store', null],
            'students.edit' => ['get', 'admin.students.edit', 'student'],
            'students.update' => ['patch', 'admin.students.update', 'student'],
            'students.destroy' => ['delete', 'admin.students.destroy', 'student'],

            'staff.index' => ['get', 'admin.staff.index', null],
            'staff.create' => ['get', 'admin.staff.create', null],
            'staff.store' => ['post', 'admin.staff.store', null],
            'staff.edit' => ['get', 'admin.staff.edit', 'staff'],
            'staff.update' => ['patch', 'admin.staff.update', 'staff'],
            'staff.destroy' => ['delete', 'admin.staff.destroy', 'staff'],
            'staff.reissue-password' => ['post', 'admin.staff.reissue-password', 'staff'],
        ];
    }

    #[DataProvider('fullAccessOnlyRoutes')]
    public function test_attendance_only_staff_cannot_access_full_access_only_routes(string $method, string $routeName, ?string $modelType)
    {
        $staff = Staff::factory()->create(['role' => 'attendance_only']);
        $url = route($routeName, $this->routeParameters($modelType));

        $response = $this->actingAs($staff, 'staff')->{$method}($url);

        $response->assertForbidden();
    }

    #[DataProvider('fullAccessOnlyRoutes')]
    public function test_full_access_staff_can_access_full_access_only_routes(string $method, string $routeName, ?string $modelType)
    {
        $staff = Staff::factory()->create(['role' => 'full_access']);
        $url = route($routeName, $this->routeParameters($modelType));

        $response = $this->actingAs($staff, 'staff')->{$method}($url);

        $this->assertNotForbidden($response);
    }

    // ------------------------------------------------------------
    // ヘルパー
    // ------------------------------------------------------------

    /**
     * ルートモデルバインディングは権限チェック（roleミドルウェア）より先に実行されるため、
     * 存在しないIDを渡すと403ではなく404になってしまう。そのため実在するレコードを作ってから渡す。
     *
     * @return array<string, mixed>
     */
    private function routeParameters(?string $modelType): array
    {
        return match ($modelType) {
            null => [],
            'guardian' => ['guardian' => Guardian::factory()->create()],
            'student' => ['student' => $this->createStudent()],
            'staff' => ['staff' => Staff::factory()->create()],
        };
    }

    /**
     * StudentFactoryはsections・school_classes・guardiansの既存レコードを参照するため、先に用意する。
     */
    private function createStudent(): Student
    {
        $this->seed([SectionSeeder::class, SchoolClassSeeder::class]);
        Guardian::factory()->create();

        return Student::factory()->create();
    }

    /**
     * @return array{Lesson, Student}
     */
    private function createLessonAndStudent(): array
    {
        $student = $this->createStudent();
        $this->seed(LessonPlanSeeder::class);

        $lesson = Lesson::create([
            'lesson_plan_id' => LessonPlan::firstOrFail()->id,
            'section_id' => Section::firstOrFail()->id,
            'date' => '2026-10-01',
        ]);

        return [$lesson, $student];
    }

    /**
     * 「権限チェックを通過できた」ことの確認。
     * コントローラー実装後はバリデーションエラーのリダイレクト等になりうるため、
     * 200かどうかではなく「403でない・ログイン画面へ戻されていない」ことを見る。
     */
    private function assertNotForbidden(TestResponse $response): void
    {
        $this->assertNotSame(403, $response->status(), 'Unexpected 403 Forbidden.');
        $this->assertFalse($response->isRedirect(route('login')), 'Unexpectedly redirected to login.');
    }
}
