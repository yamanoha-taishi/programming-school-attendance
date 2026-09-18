<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaSharedAuthUserTest extends TestCase
{
    use RefreshDatabase;

    // HandleInertiaRequests::share()はグローバルなwebミドルウェアグループの
    // 一部として、ルート個別のauth:guardian,staffミドルウェアより先に実行
    // される。以前は$request->user()（デフォルトガード＝web、実際には
    // 誰もログインしない）を使っていたため、auth:guardian,staffが付いて
    // いないルート（トップページ「/」など）では、ログイン中でも常に
    // auth.userがnullになってしまっていた。guardian・staffガードを直接
    // チェックするように修正したため、authミドルウェアが付いていない
    // ルートでも正しくログイン中のユーザーが共有されることを確認する。

    public function test_guest_sees_null_auth_user_on_the_home_page()
    {
        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user', null)
        );
    }

    public function test_authenticated_guardian_sees_their_own_auth_user_on_the_home_page()
    {
        $guardian = Guardian::factory()->create();

        $response = $this->actingAs($guardian, 'guardian')->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $guardian->id)
            ->where('auth.user.email', $guardian->email)
        );
    }

    public function test_authenticated_staff_sees_their_own_auth_user_on_the_home_page()
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff, 'staff')->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $staff->id)
            ->where('auth.user.email', $staff->email)
        );
    }
}
