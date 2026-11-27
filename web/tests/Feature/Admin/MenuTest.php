<?php

namespace Tests\Feature\Admin;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 管理メニュー画面（admin.menu）と、そこから開く一覧画面のテスト。
 * 権限（full_access のみ）は AdminAuthorizationTest で確認している。
 */
class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_page_is_rendered()
    {
        $staff = Staff::factory()->create(['role' => 'full_access']);

        $response = $this->actingAs($staff, 'staff')->get(route('admin.menu'));

        $response->assertInertia(fn ($page) => $page->component('admin/menu/index'));
    }

    /**
     * メニューから開く一覧画面。[ルート名, Inertiaページ]
     *
     * @return array<string, array{string, string}>
     */
    public static function listPages(): array
    {
        return [
            'guardians' => ['admin.guardians.index', 'admin/guardians/index'],
            'students' => ['admin.students.index', 'admin/students/index'],
            'staff' => ['admin.staff.index', 'admin/staff/index'],
        ];
    }

    // メニューの行を押したときに、エラーにならず一覧画面が開く
    #[DataProvider('listPages')]
    public function test_list_pages_linked_from_menu_are_rendered(string $routeName, string $component)
    {
        $staff = Staff::factory()->create(['role' => 'full_access']);

        $response = $this->actingAs($staff, 'staff')->get(route($routeName));

        $response->assertInertia(fn ($page) => $page->component($component));
    }
}
