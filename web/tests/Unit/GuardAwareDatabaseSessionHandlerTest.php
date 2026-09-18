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
            'auth_id' => $guardian->id,
            'guard' => 'guardian',
        ]);
    }

    public function test_records_staff_when_staff_is_authenticated()
    {
        $staff = Staff::factory()->create();
        Auth::guard('staff')->login($staff);

        $this->makeHandler()->write('test-session-staff', serialize([]));

        $this->assertDatabaseHas('sessions', [
            'id' => 'test-session-staff',
            'auth_id' => $staff->id,
            'guard' => 'staff',
        ]);
    }

    public function test_prefers_guardian_when_both_guardian_and_staff_are_authenticated_in_the_same_session()
    {
        $guardian = Guardian::factory()->create();
        $staff = Staff::factory()->create();
        Auth::guard('guardian')->login($guardian);
        Auth::guard('staff')->login($staff);

        $this->makeHandler()->write('test-session-both', serialize([]));

        // 現状の実装では、両方ログイン中の場合guardianが優先される
        // （スタッフ側は記録されない、既知の制限）
        $this->assertDatabaseHas('sessions', [
            'id' => 'test-session-both',
            'auth_id' => $guardian->id,
            'guard' => 'guardian',
        ]);
    }

    public function test_records_null_when_nobody_is_authenticated()
    {
        $this->makeHandler()->write('test-session-guest', serialize([]));

        $this->assertDatabaseHas('sessions', [
            'id' => 'test-session-guest',
            'auth_id' => null,
            'guard' => null,
        ]);
    }
}
