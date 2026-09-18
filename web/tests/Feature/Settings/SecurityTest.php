<?php

namespace Tests\Feature\Settings;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        /* @chisel-passkeys */
        Features::passkeys([
            'confirmPassword' => true,
        ]);
        /* @end-chisel-passkeys */

        $guardian = Guardian::factory()->create();

        $this->actingAs($guardian, 'guardian')
            /* @chisel-password-confirmation */
            ->withSession(['auth.password_confirmed_at' => time()])
            /* @end-chisel-password-confirmation */
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                /* @chisel-passkeys */
                ->where('canManagePasskeys', true)
                ->where('passkeys', [])
                /* @end-chisel-passkeys */
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false),
            );
    }

    /* @chisel-password-confirmation */
    public function test_security_page_requires_password_confirmation_when_enabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $guardian = Guardian::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($guardian, 'guardian')
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }
    /* @end-chisel-password-confirmation */

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $guardian = Guardian::factory()->create();

        $this->actingAs($guardian, 'guardian')
            /* @chisel-password-confirmation */
            ->withSession(['auth.password_confirmed_at' => time()])
            /* @end-chisel-password-confirmation */
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                /* @chisel-passkeys */
                ->where('canManagePasskeys', false)
                ->where('passkeys', [])
                /* @end-chisel-passkeys */
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_password_can_be_updated()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $guardian->refresh()->password));
    }

    public function test_staff_can_also_update_their_password()
    {
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($staff, 'staff')
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $staff->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }
}
