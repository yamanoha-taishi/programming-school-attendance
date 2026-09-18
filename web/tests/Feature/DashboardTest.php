<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_guardians_can_visit_the_dashboard()
    {
        $guardian = Guardian::factory()->create();
        $this->actingAs($guardian, 'guardian');

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_authenticated_staff_can_visit_the_dashboard()
    {
        $staff = Staff::factory()->create();
        $this->actingAs($staff, 'staff');

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }
}
