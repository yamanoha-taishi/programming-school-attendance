<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianAndStaffMemberCodeUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_member_code_can_be_reused_after_the_previous_owner_is_soft_deleted()
    {
        $deletedGuardian = Guardian::factory()->create(['member_code' => '0001']);
        $deletedGuardian->delete();

        $guardian = Guardian::factory()->create(['member_code' => '0001']);

        $this->assertSame('0001', $guardian->member_code);
    }

    public function test_staff_member_code_can_be_reused_after_the_previous_owner_is_soft_deleted()
    {
        $deletedStaff = Staff::factory()->create(['member_code' => '5001']);
        $deletedStaff->delete();

        $staff = Staff::factory()->create(['member_code' => '5001']);

        $this->assertSame('5001', $staff->member_code);
    }

    public function test_active_guardians_cannot_share_the_same_member_code()
    {
        Guardian::factory()->create(['member_code' => '0002']);

        $this->expectException(QueryException::class);

        Guardian::factory()->create(['member_code' => '0002']);
    }

    public function test_active_staff_cannot_share_the_same_member_code()
    {
        Staff::factory()->create(['member_code' => '5002']);

        $this->expectException(QueryException::class);

        Staff::factory()->create(['member_code' => '5002']);
    }
}
