<?php

namespace Tests\Feature\Auth;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_guardians_can_authenticate_using_the_login_screen()
    {
        $guardian = Guardian::factory()->create();

        $response = $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($guardian, 'guardian');

        $response->assertRedirect('/');
    }

    public function test_staff_can_authenticate_using_the_login_screen()
    {
        $staff = Staff::factory()->create();

        $response = $this->post(route('login'), [
            'member_code' => $staff->member_code,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($staff, 'staff');

        $response->assertRedirect('/');
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $guardian = Guardian::factory()->create();

        $response = $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('member_code');
        $this->assertGuest('guardian');
        $this->assertGuest('staff');
    }

    public function test_users_can_not_authenticate_with_nonexistent_member_code()
    {
        $response = $this->post(route('login'), [
            'member_code' => '9999',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('member_code');
        $this->assertGuest('guardian');
        $this->assertGuest('staff');
    }

    public function test_login_with_non_string_member_code_is_rejected_with_a_validation_error()
    {
        // member_codeに配列を送ると、文字列であることを前提にしている
        // Where句・レートリミッタのStr::lower()呼び出しが壊れて500に
        // なってしまうため、バリデーションの時点で弾かれることを確認する。
        $response = $this->post(route('login'), [
            'member_code' => ['0001', '0002'],
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('member_code');
        $this->assertGuest('guardian');
        $this->assertGuest('staff');
    }

    public function test_login_with_wrong_length_member_code_is_rejected_with_a_validation_error()
    {
        // member_codeは4桁固定の仕様。5桁など長さが違う値は、DB照会に
        // 到達する前にバリデーションで弾かれることを確認する。
        $response = $this->post(route('login'), [
            'member_code' => '12345',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('member_code');
        $this->assertGuest('guardian');
        $this->assertGuest('staff');
    }

    public function test_users_can_logout()
    {
        $guardian = Guardian::factory()->create();

        $response = $this->actingAs($guardian, 'guardian')->post(route('logout'));

        $response->assertRedirect('/');

        $this->assertGuest('guardian');
    }

    public function test_staff_can_logout()
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff, 'staff')->post(route('logout'));

        $response->assertRedirect('/');

        $this->assertGuest('staff');
    }

    public function test_logout_clears_both_guards_when_both_are_authenticated()
    {
        $guardian = Guardian::factory()->create();
        $staff = Staff::factory()->create();

        $this->actingAs($guardian, 'guardian');
        $this->actingAs($staff, 'staff');

        $response = $this->post(route('logout'));

        $response->assertRedirect('/');

        $this->assertGuest('guardian');
        $this->assertGuest('staff');
    }

    public function test_users_are_rate_limited()
    {
        $this->freezeTime();

        $guardian = Guardian::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'member_code' => $guardian->member_code,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from(route('login'))->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'wrong-password',
        ]);

        $this->assertLoginThrottled($response);
    }

    public function test_successful_login_clears_the_rate_limiter_for_that_member_code()
    {
        // throttleミドルウェアは成功・失敗を区別せず必ずhit()するため、
        // ログイン成功時に明示的にクリアしないと、数回パスワードを
        // 間違えてから成功した直後にその1分間ログインし直せなくなって
        // しまう（別端末での再ログインや、ログアウト後すぐの再ログインを
        // 妨げる）。ここでは上限の5回未満（4回）だけ失敗させたあと成功させ、
        // 直後にもう一度正しい情報でログインできることを確認する。
        $guardian = Guardian::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login'), [
                'member_code' => $guardian->member_code,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($guardian, 'guardian');

        $this->post(route('logout'));

        $response = $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($guardian, 'guardian');
    }

    public function test_successful_login_does_not_clear_the_ip_wide_rate_limit_bucket()
    {
        // member_code単位のリミット解除（クリア）がIP単位のリミットまで
        // 巻き添えでクリアしてしまうと、正しい資格情報を1つ持つ攻撃者が
        // 「複数のmember_codeを試す→自分の正しい情報でログイン成功→IP
        // バケットがリセットされる」を繰り返すことで、IP単位の上限
        // （パスワードスプレー対策）を実質無効化できてしまう。ここでは
        // 19回別々のmember_codeで失敗させたあと、20回目に正しい資格情報
        // でログインを成功させ、直後の21回目（また別のmember_code）が
        // 依然としてIP単位の上限でブロックされることを確認する
        // （IPバケットが巻き添えでクリアされていれば、この21回目は
        // 通ってしまうはず）。
        $this->freezeTime();

        $guardians = Guardian::factory()->count(19)->create();

        foreach ($guardians as $guardian) {
            $this->post(route('login'), [
                'member_code' => $guardian->member_code,
                'password' => 'wrong-password',
            ]);
        }

        $legitimateGuardian = Guardian::factory()->create();

        $this->post(route('login'), [
            'member_code' => $legitimateGuardian->member_code,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($legitimateGuardian, 'guardian');

        $this->post(route('logout'));

        $anotherGuardian = Guardian::factory()->create();

        $response = $this->from(route('login'))->post(route('login'), [
            'member_code' => $anotherGuardian->member_code,
            'password' => 'wrong-password',
        ]);

        $this->assertLoginThrottled($response);
    }

    public function test_users_are_rate_limited_across_different_member_codes_from_the_same_ip()
    {
        // member_codeは4桁と空間が狭く、member_code単位のリミット（5回/分）
        // だけでは同一IPから会員番号を変えながら試すパスワードスプレー攻撃を
        // 防げない。ここでは1つのmember_codeにつき1回しか試さない（個々の
        // バケットは5回に届かない）が、IP単位の上限（20回/分）には到達し、
        // 遮断されることを確認する。
        $this->freezeTime();

        $guardians = Guardian::factory()->count(20)->create();

        foreach ($guardians as $guardian) {
            $this->post(route('login'), [
                'member_code' => $guardian->member_code,
                'password' => 'wrong-password',
            ]);
        }

        $anotherGuardian = Guardian::factory()->create();

        $response = $this->from(route('login'))->post(route('login'), [
            'member_code' => $anotherGuardian->member_code,
            'password' => 'wrong-password',
        ]);

        $this->assertLoginThrottled($response);
    }

    /**
     * レート制限に達したときに、429のエラーページではなく試行回数超過の
     * エラーメッセージ付きでログイン画面に戻されることを確認する。
     * member_codeにエラーがあることだけでなく文言まで比較するのは、
     * 通常のログイン失敗（auth.failed）と区別するため。秒数を60に
     * 固定できるよう、呼び出し側のテストではfreezeTime()しておく。
     */
    private function assertLoginThrottled(TestResponse $response): void
    {
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'member_code' => __('auth.throttle', ['seconds' => 60]),
        ]);
    }
}
