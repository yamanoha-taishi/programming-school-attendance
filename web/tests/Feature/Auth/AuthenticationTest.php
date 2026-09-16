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
}
