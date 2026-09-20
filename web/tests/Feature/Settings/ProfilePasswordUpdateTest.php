<?php

namespace Tests\Feature\Settings;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_password_can_be_updated()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('new-password', $guardian->fresh()->password));
    }

    public function test_password_update_targets_the_most_recently_logged_in_guard_when_both_are_authenticated()
    {
        // 同一ブラウザで保護者としてログイン中に、スタッフとして
        // ログインし直した場合、パスワード変更の対象はスタッフ自身に
        // なるべき（App\Http\Middleware\Authenticateがactive_guardを
        // 優先することの、settings/profile/password経路でのE2E検証）。
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

        $response = $this->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('new-password', $staff->fresh()->password));
        $this->assertTrue(Hash::check('password', $guardian->fresh()->password));

        // 現在のセッション自体は維持されるため、両ガードとも
        // ログインしたままのはず。
        $this->assertAuthenticatedAs($guardian, 'guardian');
        $this->assertAuthenticatedAs($staff, 'staff');
    }

    public function test_staff_password_can_be_updated()
    {
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($staff, 'staff')
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('new-password', $staff->fresh()->password));
    }

    public function test_correct_current_password_must_be_provided_to_update_password()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('password', $guardian->fresh()->password));
    }

    public function test_new_password_must_be_confirmed()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $guardian->fresh()->password));
    }

    public function test_updating_password_does_not_invalidate_unrelated_sessions()
    {
        $guardian = Guardian::factory()->create();
        $otherGuardian = Guardian::factory()->create();

        // 別の保護者のセッション（本人のパスワード変更に巻き込まれてはいけない）
        DB::table('sessions')->insert([
            'id' => 'unrelated-guardian-session',
            'guardian_id' => $otherGuardian->id,
            'staff_id' => null,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $this
            ->actingAs($guardian, 'guardian')
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-guardian-session']);
    }

    public function test_updating_password_invalidates_other_devices_sessions_for_the_same_account()
    {
        $guardian = Guardian::factory()->create();

        // 別デバイスでログイン中だったセッションを想定して直接1行仕込む
        DB::table('sessions')->insert([
            'id' => 'other-device-session',
            'guardian_id' => $guardian->id,
            'staff_id' => null,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-device-session',
        ]);

        // 変更した本人はログアウトされず、そのまま使い続けられること
        $this->assertAuthenticatedAs($guardian, 'guardian');
    }

    public function test_password_update_invalidates_the_other_browsers_session_but_keeps_the_current_one()
    {
        // phpunit.xmlはSESSION_DRIVER=arrayを強制しているため、実際の
        // GuardAwareDatabaseSessionHandlerを通した「自分のセッション行は
        // 消えずに残る」ことまではこのテストでしか検証できない。
        config(['session.driver' => 'guard-aware-database']);

        $guardian = Guardian::factory()->create();
        $cookieName = config('session.cookie');

        $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ])->assertRedirect('/');

        $currentSessionId = $this->app['session']->getId();

        // 他デバイスでログインしたままだったセッションを想定して直接1行仕込む
        DB::table('sessions')->insert([
            'id' => 'other-device-session',
            'guardian_id' => $guardian->id,
            'staff_id' => null,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $response = $this
            ->withCookie($cookieName, $currentSessionId)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        // 他デバイスのセッションは削除されている
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);

        // いま使っているこのセッションは削除されず、ログインしたまま
        // プロフィール画面を開き続けられること
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);

        $this->withCookie($cookieName, $currentSessionId)
            ->get(route('profile.edit'))
            ->assertOk();
    }
}
