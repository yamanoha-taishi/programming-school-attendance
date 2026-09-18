<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetLinkMail;
use App\Mail\PasswordResetNotRegisteredMail;
use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        $response = $this->get(route('password.reset', 'some-token'));

        $response->assertOk();
    }

    public function test_reset_link_is_sent_when_guardian_email_is_registered()
    {
        Mail::fake();

        $guardian = Guardian::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $guardian->email]);

        $response->assertSessionHas('status', __('auth.reset_link_sent'));

        Mail::assertQueued(PasswordResetLinkMail::class, function ($mail) use ($guardian) {
            return $mail->hasTo($guardian->email);
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $guardian->email,
            'guard' => 'guardian',
        ]);
    }

    public function test_reset_link_is_sent_when_staff_email_is_registered()
    {
        Mail::fake();

        $staff = Staff::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $staff->email]);

        $response->assertSessionHas('status', __('auth.reset_link_sent'));

        Mail::assertQueued(PasswordResetLinkMail::class, function ($mail) use ($staff) {
            return $mail->hasTo($staff->email);
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $staff->email,
            'guard' => 'staff',
        ]);
    }

    public function test_not_registered_notice_is_sent_when_email_is_not_registered()
    {
        Mail::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        $response->assertSessionHas('status', __('auth.reset_link_sent'));

        Mail::assertQueued(PasswordResetNotRegisteredMail::class, function ($mail) {
            return $mail->hasTo('nobody@example.com');
        });

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nobody@example.com',
        ]);
    }

    public function test_reset_links_are_sent_independently_when_guardian_and_staff_share_email()
    {
        Mail::fake();

        $email = 'shared@example.com';
        $guardian = Guardian::factory()->create(['email' => $email]);
        $staff = Staff::factory()->create(['email' => $email]);

        $response = $this->post(route('password.email'), ['email' => $email]);

        $response->assertSessionHas('status', __('auth.reset_link_sent'));

        Mail::assertQueued(PasswordResetLinkMail::class, 2);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $email,
            'guard' => 'guardian',
        ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $email,
            'guard' => 'staff',
        ]);
    }

    public function test_forgot_password_requests_are_rate_limited()
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'someone@example.com']);
        }

        $response = $this->post(route('password.email'), ['email' => 'someone@example.com']);

        $response->assertTooManyRequests();
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        $guardian = Guardian::factory()->create();
        $token = 'plain-text-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $guardian->email,
            'guard' => 'guardian',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $guardian->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $guardian->fresh()->password));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $guardian->email,
            'guard' => 'guardian',
        ]);
    }

    public function test_password_cannot_be_reset_with_invalid_token()
    {
        $guardian = Guardian::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $guardian->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_password_cannot_be_reset_with_expired_token()
    {
        $guardian = Guardian::factory()->create();
        $token = 'plain-text-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $guardian->email,
            'guard' => 'guardian',
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $guardian->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_password_reset_invalidates_other_sessions_for_that_account()
    {
        $guardian = Guardian::factory()->create();
        $token = 'plain-text-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $guardian->email,
            'guard' => 'guardian',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // 他デバイスでログイン中だったセッションを想定して直接1行仕込む
        DB::table('sessions')->insert([
            'id' => 'other-device-session',
            'auth_id' => $guardian->id,
            'guard' => 'guardian',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $guardian->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-device-session',
        ]);
    }

    public function test_password_reset_does_not_invalidate_unrelated_sessions()
    {
        $guardian = Guardian::factory()->create();
        $otherGuardian = Guardian::factory()->create();
        $staff = Staff::factory()->create();
        $token = 'plain-text-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $guardian->email,
            'guard' => 'guardian',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // 別の保護者のセッション
        DB::table('sessions')->insert([
            'id' => 'unrelated-guardian-session',
            'auth_id' => $otherGuardian->id,
            'guard' => 'guardian',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        // 同一IDが偶然スタッフ側に存在するケース（idではなくguardで区別されるべき）
        DB::table('sessions')->insert([
            'id' => 'unrelated-staff-session',
            'auth_id' => $guardian->id,
            'guard' => 'staff',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $guardian->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-guardian-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-staff-session']);
    }

    public function test_guardian_and_staff_can_each_independently_reset_password_when_sharing_email()
    {
        $email = 'shared@example.com';
        $guardian = Guardian::factory()->create(['email' => $email]);
        $staff = Staff::factory()->create(['email' => $email]);

        $guardianToken = 'guardian-plain-token';
        $staffToken = 'staff-plain-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'guard' => 'guardian',
            'token' => Hash::make($guardianToken),
            'created_at' => now(),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'guard' => 'staff',
            'token' => Hash::make($staffToken),
            'created_at' => now(),
        ]);

        // 保護者が自分のトークンで再設定する
        $response = $this->post(route('password.update'), [
            'token' => $guardianToken,
            'email' => $email,
            'password' => 'new-guardian-password',
            'password_confirmation' => 'new-guardian-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-guardian-password', $guardian->fresh()->password));
        // スタッフのパスワードは変わっていないこと
        $this->assertTrue(Hash::check('password', $staff->fresh()->password));

        // 保護者のトークンだけ消費され、スタッフのトークンは残っていること
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $email,
            'guard' => 'guardian',
        ]);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $email,
            'guard' => 'staff',
        ]);

        // スタッフも自分のトークンで独立して再設定できること
        $response = $this->post(route('password.update'), [
            'token' => $staffToken,
            'email' => $email,
            'password' => 'new-staff-password',
            'password_confirmation' => 'new-staff-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-staff-password', $staff->fresh()->password));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $email,
            'guard' => 'staff',
        ]);
    }

    public function test_reset_password_requests_are_rate_limited()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => 'someone@example.com',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
        }

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'someone@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertTooManyRequests();
    }
}
