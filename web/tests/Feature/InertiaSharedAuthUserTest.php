<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class InertiaSharedAuthUserTest extends TestCase
{
    use RefreshDatabase;

    // HandleInertiaRequests::share()はグローバルなwebミドルウェアグループの
    // 一部として、ルート個別のauth:guardian,staffミドルウェアより先に実行
    // される。以前は$request->user()（デフォルトガード＝web、実際には
    // 誰もログインしない）を使っていたため、auth:guardian,staffが付いて
    // いないルート（トップページ「/」など）では、ログイン中でも常に
    // auth.userがnullになってしまっていた。guardian・staffガードを直接
    // チェックするように修正したため、authミドルウェアが付いていない
    // ルートでも正しくログイン中のユーザーが共有されることを確認する。
    //
    // ここでは意図的にactingAs()を使わない。actingAs()は内部で
    // Auth::shouldUse()を呼び、デフォルトガード自体を切り替えてしまうため、
    // 修正前の$request->user()を使った旧実装でもテストが通ってしまい、
    // 回帰を検出できない。実際のログインルートをPOSTすることで、
    // デフォルトガードを一切切り替えずに検証する。

    public function test_guest_sees_null_auth_user_on_the_home_page()
    {
        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user', null)
        );
    }

    public function test_authenticated_guardian_sees_their_own_auth_user_on_the_home_page()
    {
        $guardian = Guardian::factory()->create();

        $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $guardian->id)
            ->where('auth.user.email', $guardian->email)
        );
    }

    public function test_authenticated_staff_sees_their_own_auth_user_on_the_home_page()
    {
        $staff = Staff::factory()->create();

        $this->post(route('login'), [
            'member_code' => $staff->member_code,
            'password' => 'password',
        ]);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $staff->id)
            ->where('auth.user.email', $staff->email)
        );
    }

    public function test_auth_user_is_limited_to_id_name_and_email()
    {
        // Guardian・Staffモデルはpassword以外を隠していないため、
        // フルの属性を共有してしまうとnote（運用側の特記事項）等の
        // 内部情報が全ページのHTMLに露出してしまう。id/name/emailだけに
        // 絞られていることを確認する。
        $guardian = Guardian::factory()->create(['note' => '月謝滞納あり／要注意']);

        $this->post(route('login'), [
            'member_code' => $guardian->member_code,
            'password' => 'password',
        ]);

        $response = $this->get('/');

        // etc()を付けない＝id/name/email以外の一切のキーが無いことまで
        // 厳密に検証する（missing()は特に紛れ込みやすいキーを明示するため
        // の冗長な保険として残す）。
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user', fn ($user) => $user
                ->where('id', $guardian->id)
                ->where('name', $guardian->name)
                ->where('email', $guardian->email)
                ->missing('note')
                ->missing('member_code')
                ->missing('deleted_at')
            )
        );
    }

    public function test_auth_user_reflects_whichever_guard_logged_in_most_recently_when_both_are_authenticated()
    {
        // 同一ブラウザで保護者としてログイン中に、スタッフとしてログイン
        // し直した場合は、以降スタッフ側の画面として振る舞うべき
        // （旧実装はguardianが常に優先されてしまっていた）。
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

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $staff->id)
            ->where('auth.user.email', $staff->email)
        );

        $this->assertAuthenticatedAs($guardian, 'guardian');
        $this->assertAuthenticatedAs($staff, 'staff');
    }

    public function test_stale_active_guard_does_not_regain_priority_after_the_account_is_restored()
    {
        // 保護者・スタッフ両方にログイン中で、直近ログインはスタッフ
        // （active_guard='staff'）とする。この状態でスタッフのアカウントが
        // （将来の管理者操作等で）論理削除されると、Auth::guard('staff')
        // ->check()は一時的にfalseになる（Eloquentの既定スコープにより、
        // 論理削除済みのスタッフはuser providerから見えなくなるため）。
        //
        // このとき、もしactive_guardの値（'staff'）をセッションに残した
        // ままにしておくと、後日スタッフのアカウントが復元された際、
        // 削除・復元の間もずっとログインし続けていた保護者を差し置いて、
        // 「直近ログインはスタッフ」という古い優先順位がそのまま復活して
        // しまう。HandleInertiaRequests::share()がstale化した時点で
        // active_guardを消しておくことで、復元後も優先順位が不当に
        // 復活しないことを確認する。
        //
        // なお、スタッフ自身のガードとしてのログイン状態（Auth::guard
        // ('staff')->check()）自体は、論理削除・復元というアカウントの
        // 状態変化だけでは失効しない（削除中はDBから見えなくなるため
        // 一時的にfalseになるが、復元されれば元のセッションのまま再びtrueに
        // 戻る）。これは「論理削除時にセッションを強制失効させるべきか」
        // という別の（より大きな）論点であり、ここでは扱わない。
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

        $this->assertEquals('staff', session('active_guard'));

        $staff->delete();

        // PHPUnitのテストでは$this->appが1テスト内で使い回されるため、
        // Auth::guard('staff')が一度解決されると、以降のcheck()は
        // （実際のHTTPリクエストと違い）DBを再確認せずキャッシュされた
        // ユーザーインスタンスをそのまま返してしまう。forgetGuards()で
        // 明示的に解決済みガードを破棄し、実際の別リクエストと同じように
        // DBから読み直させる。
        Auth::forgetGuards();

        $response = $this->get('/');

        // 削除中は保護者が表示される（スタッフのcheck()がfalseになり、
        // 既存の優先順位判定ロジックが保護者側へフォールバックするため）。
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $guardian->id)
        );

        $this->assertNull(session('active_guard'));

        $staff->restore();
        Auth::forgetGuards();

        $response = $this->get('/');

        // 復元後も、スタッフが「直近ログイン」として優先されることはなく、
        // ずっとログインし続けていた保護者が引き続き表示される。
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $guardian->id)
        );

        $this->assertAuthenticatedAs($guardian, 'guardian');
        $this->assertAuthenticatedAs($staff, 'staff');
    }
}
