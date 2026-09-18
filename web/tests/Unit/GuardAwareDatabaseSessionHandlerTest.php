<?php

namespace Tests\Unit;

use App\Extensions\GuardAwareDatabaseSessionHandler;
use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuardAwareDatabaseSessionHandlerTest extends TestCase
{
    use RefreshDatabase;

    private function makeHandler(): GuardAwareDatabaseSessionHandler
    {
        return new GuardAwareDatabaseSessionHandler(
            DB::connection(),
            'sessions',
            config('session.lifetime'),
            app()
        );
    }

    public function test_records_guardian_when_guardian_is_authenticated()
    {
        $guardian = Guardian::factory()->create();
        Auth::guard('guardian')->login($guardian);

        $this->makeHandler()->write('test-session-guardian', serialize([]));

        $this->assertDatabaseHas('sessions', [
            'id' => 'test-session-guardian',
            'guardian_id' => $guardian->id,
            'staff_id' => null,
        ]);
    }

    public function test_records_staff_when_staff_is_authenticated()
    {
        $staff = Staff::factory()->create();
        Auth::guard('staff')->login($staff);

        $this->makeHandler()->write('test-session-staff', serialize([]));

        $this->assertDatabaseHas('sessions', [
            'id' => 'test-session-staff',
            'guardian_id' => null,
            'staff_id' => $staff->id,
        ]);
    }

    public function test_records_both_when_guardian_and_staff_are_authenticated_in_the_same_session()
    {
        $guardian = Guardian::factory()->create();
        $staff = Staff::factory()->create();
        Auth::guard('guardian')->login($guardian);
        Auth::guard('staff')->login($staff);

        $this->makeHandler()->write('test-session-both', serialize([]));

        // guardian_id・staff_idそれぞれ専用カラムを持つため、
        // 両方に同時ログインしていても両方とも記録される。
        $this->assertDatabaseHas('sessions', [
            'id' => 'test-session-both',
            'guardian_id' => $guardian->id,
            'staff_id' => $staff->id,
        ]);
    }

    public function test_records_null_when_nobody_is_authenticated()
    {
        $this->makeHandler()->write('test-session-guest', serialize([]));

        $this->assertDatabaseHas('sessions', [
            'id' => 'test-session-guest',
            'guardian_id' => null,
            'staff_id' => null,
        ]);
    }
}
