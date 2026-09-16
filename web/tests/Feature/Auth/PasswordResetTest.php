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

        Mail::assertSent(PasswordResetLinkMail::class, function ($mail) use ($guardian) {
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

        Mail::assertSent(PasswordResetLinkMail::class, function ($mail) use ($staff) {
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

        Mail::assertSent(PasswordResetNotRegisteredMail::class, function ($mail) {
            return $mail->hasTo('nobody@example.com');
        });

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nobody@example.com',
        ]);
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
}
