<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Staff;
use App\Providers\RateLimitServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // member_code・passwordを配列などの非文字列で送ると、下のWhere句や
        // レートリミッタのStr::lower()呼び出しで500エラーになってしまうため、
        // ここで文字列であることを検証しておく。member_codeは4桁固定
        // （0001〜9999）の仕様なのでdigitsで形式も合わせて検証する。
        $validated = $request->validate([
            'member_code' => 'required|string|digits:4',
            'password' => 'required|string',
        ]);

        // 入力された会員番号が保護者のものかチェック
        $guardian = Guardian::where('member_code', $validated['member_code'])->first();

        if ($guardian && Hash::check($validated['password'], $guardian->password)) {
            Auth::guard('guardian')->login($guardian);
            $request->session()->regenerate();
            // 直近でログインしたガードを記録する。同一ブラウザで既に
            // 他方のガードでもログイン中だった場合に、どちらを「今の
            // ログインユーザー」として扱うかの判定に使う
            // （App\Http\Middleware\Authenticate・HandleInertiaRequests参照）。
            $request->session()->put('active_guard', 'guardian');
            $this->clearLoginRateLimiter($request);

            return redirect()->intended('/');
        }

        // 保護者ではなかった場合、スタッフのものかチェック
        $staff = Staff::where('member_code', $validated['member_code'])->first();

        if ($staff && Hash::check($validated['password'], $staff->password)) {
            Auth::guard('staff')->login($staff);
            $request->session()->regenerate();
            $request->session()->put('active_guard', 'staff');
            $this->clearLoginRateLimiter($request);

            return redirect()->intended('/');
        }

        // 保護者・スタッフのどちらにも一致しなかった場合
        throw ValidationException::withMessages([
            'member_code' => [__('auth.failed')],
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::guard('guardian')->check()) {
            Auth::guard('guardian')->logout();
        }

        if (Auth::guard('staff')->check()) {
            Auth::guard('staff')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * ログイン成功時に、そのリクエストが消費していたmember_code単位の
     * レート制限を解除する。throttleミドルウェアは成功・失敗を区別せず
     * 必ずhit()するため、これを呼ばないと数回失敗してから成功した直後に
     * その1分間ログインし直せなくなってしまう。
     *
     * IP単位のリミット（login-ip:...）は意図的にクリアしない：もしここで
     * 一緒にクリアすると、正しい資格情報を1つ持つ攻撃者が「複数の
     * member_codeを試す→自分の正しい情報でログイン成功→IPバケットが
     * リセットされる」を繰り返すことで、IP単位の上限（パスワード
     * スプレー対策）を実質無効化できてしまう。
     *
     * ThrottleRequestsミドルウェアが実際に使うキーは
     * md5($limiterName.$limit->key)（Laravelのデフォルトでキーをハッシュ化
     * する設定）のため、RateLimitServiceProviderで組み立てた生キーに対して
     * 同じ変換をここでも行う。
     */
    private function clearLoginRateLimiter(Request $request): void
    {
        $memberCodeKey = RateLimitServiceProvider::loginThrottleKeys($request)['memberCode'];

        RateLimiter::clear(md5('login'.$memberCodeKey));
    }
}
