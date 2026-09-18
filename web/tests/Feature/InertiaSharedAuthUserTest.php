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
    //
    // ここでは意図的にactingAs()を使わない。actingAs()は内部で
    // Auth::shouldUse()を呼び、デフォルトガード自体を切り替えてしまうため、
    // 修正前の$request->user()を使った旧実装でもテストが通ってしまい、
    // 回帰を検出できない。実際のログインルートをPOSTすることで、
    // デフォルトガードを一切切り替えずに検証する。

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

        $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $guardian->id)
            ->where('auth.user.email', $guardian->email)
        );
    }

    public function test_authenticated_staff_sees_their_own_auth_user_on_the_home_page()
    {
        $staff = Staff::factory()->create();

        $this->post(route('login'), [
            'member_code' => $staff->member_code,
            'password' => 'password',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $staff->id)
            ->where('auth.user.email', $staff->email)
        );
    }

    public function test_auth_user_is_limited_to_id_name_and_email()
    {
        // Guardian・Staffモデルはpassword以外を隠していないため、
        // フルの属性を共有してしまうとnote（運用側の特記事項）等の
        // 内部情報が全ページのHTMLに露出してしまう。id/name/emailだけに
        // 絞られていることを確認する。
        $guardian = Guardian::factory()->create(['note' => '月謝滞納あり／要注意']);

        $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->has('auth.user', fn ($user) => $user
                ->where('id', $guardian->id)
                ->where('name', $guardian->name)
                ->where('email', $guardian->email)
                ->missing('note')
                ->missing('member_code')
                ->missing('deleted_at')
                ->etc()
            )
        );
    }

    public function test_auth_user_reflects_whichever_guard_logged_in_most_recently_when_both_are_authenticated()
    {
        // 同一ブラウザで保護者としてログイン中に、スタッフとしてログイン
        // し直した場合は、以降スタッフ側の画面として振る舞うべき
        // （旧実装はguardianが常に優先されてしまっていた）。
        $guardian = Guardian::factory()->create();
        $staff = Staff::factory()->create();

        $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $this->post(route('login'), [
            'member_code' => $staff->member_code,
            'password' => 'password',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $staff->id)
            ->where('auth.user.email', $staff->email)
        );

        $this->assertAuthenticatedAs($guardian, 'guardian');
        $this->assertAuthenticatedAs($staff, 'staff');
    }
}
