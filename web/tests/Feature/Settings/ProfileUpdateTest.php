<?php

namespace Tests\Feature\Settings;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_staff_can_also_view_the_profile_page()
    {
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($staff, 'staff')
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->patch(route('profile.update'), [
                'name' => 'Test Guardian',
                'email' => 'test-guardian@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $guardian->refresh();

        $this->assertSame('Test Guardian', $guardian->name);
        $this->assertSame('test-guardian@example.com', $guardian->email);
    }

    public function test_profile_email_must_be_unique_among_the_same_guard()
    {
        $guardian = Guardian::factory()->create();
        $otherGuardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->patch(route('profile.update'), [
                'name' => $guardian->name,
                'email' => $otherGuardian->email,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_profile_email_can_match_an_account_in_the_other_guard()
    {
        // guardianとstaffが同じメールアドレスを共有することは仕様上許容されているため、
        // 一意性チェックが誤ってブロックしないことを確認する。
        $guardian = Guardian::factory()->create();
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->patch(route('profile.update'), [
                'name' => $guardian->name,
                'email' => $staff->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame($staff->email, $guardian->fresh()->email);
    }

    public function test_profile_email_can_be_reused_after_the_previous_owner_is_soft_deleted()
    {
        // guardian・staffはSoftDeletesを使っているため、退会済みアカウントの
        // 行はDBに残り続ける。そのメールアドレスを別の新しいguardianが
        // 使えることを確認する（バリデーション・DBのユニークインデックス
        // 両方が論理削除済みの行を除外できている必要がある）。
        $deletedGuardian = Guardian::factory()->create(['email' => 'reused@example.com']);
        $deletedGuardian->delete();

        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->patch(route('profile.update'), [
                'name' => $guardian->name,
                'email' => 'reused@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('reused@example.com', $guardian->fresh()->email);
    }

    public function test_user_can_delete_their_account()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest('guardian');

        // GuardianはSoftDeletesを使っているため、行自体は消えず
        // deleted_atが入る（fresh()はグローバルスコープを無視するため
        // 論理削除後も行を取得できる。物理削除ではないことに注意）。
        $this->assertSoftDeleted($guardian);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $guardian = Guardian::factory()->create();

        $response = $this
            ->actingAs($guardian, 'guardian')
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($guardian->fresh());
    }
}
