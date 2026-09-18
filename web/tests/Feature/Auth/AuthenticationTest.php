<?php

namespace Tests\Feature\Auth;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $guardian = Guardian::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'member_code' => $guardian->member_code,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }

    public function test_successful_login_clears_the_rate_limiter_for_that_member_code_and_ip()
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

    public function test_users_are_rate_limited_across_different_member_codes_from_the_same_ip()
    {
        // member_codeは4桁と空間が狭く、member_code単位のリミット（5回/分）
        // だけでは同一IPから会員番号を変えながら試すパスワードスプレー攻撃を
        // 防げない。ここでは1つのmember_codeにつき1回しか試さない（個々の
        // バケットは5回に届かない）が、IP単位の上限（20回/分）には到達し、
        // 遮断されることを確認する。
        $guardians = Guardian::factory()->count(20)->create();

        foreach ($guardians as $guardian) {
            $this->post(route('login'), [
                'member_code' => $guardian->member_code,
                'password' => 'wrong-password',
            ]);
        }

        $anotherGuardian = Guardian::factory()->create();

        $response = $this->post(route('login'), [
            'member_code' => $anotherGuardian->member_code,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
