<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $guardian = Guardian::where('email', $validated['email'])->first();
        $staff = Staff::where('email', $validated['email'])->first();

        $matched = null;

        foreach ([['guardian', $guardian], ['staff', $staff]] as [$guard, $user]) {
            if (! $user) {
                continue;
            }

            $tokenRecord =
                DB::table('password_reset_tokens')
                    ->where('email', $validated['email'])
                    ->where('guard', $guard)
                    ->first();

            if ($tokenRecord && Hash::check($validated['token'], $tokenRecord->token)) {
                $matched = [
                    'user' => $user,
                    'guard' => $guard,
                    'tokenRecord' => $tokenRecord,
                ];
                break;
            }

        }

        if (! $matched) {
            throw ValidationException::withMessages([
                'email' => [__('auth.reset_token_invalid')],
            ]);
        }

        if (now()->diffInMinutes($matched['tokenRecord']->created_at, absolute: true) > 60) {
            DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->where('guard', $matched['guard'])
                ->delete();

            throw ValidationException::withMessages([
                'email' => [__('auth.reset_token_expired')],
            ]);
        }

        $matched['user']->password = $validated['password'];
        $matched['user']->save();

        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->where('guard', $matched['guard'])
            ->delete();

        // guardianなら guardian_id、staffなら staff_id カラムを対象に、
        // 該当アカウントがログイン中の全セッションを無効化する。
        $sessionColumn = $matched['guard'] === 'guardian' ? 'guardian_id' : 'staff_id';

        DB::table('sessions')
            ->where($sessionColumn, $matched['user']->id)
            ->delete();

        // 上のDB削除は、Laravelのセッション機構を経由しない直接操作のため、
        // 「今まさにこのリクエストを送っているセッション自身」が削除対象に
        // 含まれていた場合、リクエスト終了時にLaravelが古いセッションIDの
        // 行を空の状態で復活させてしまう。これを防ぐため、今アクセス中の
        // ブラウザ自身がリセット対象のアカウントでログイン中だった場合は、
        // ログアウトと同様にセッション自体を明示的に無効化し、新しい
        // セッションIDを発行させる。
        if (Auth::guard($matched['guard'])->check()
            && Auth::guard($matched['guard'])->id() === $matched['user']->id) {
            // 同じセッションでもう一方のガードにもログイン中の場合、
            // セッションが道連れで切れる以上、ログアウト処理と同様に
            // 明示的にログアウトさせておく（Authの内部キャッシュを確実にクリアする）。
            if (Auth::guard('guardian')->check()) {
                Auth::guard('guardian')->logout();
            }

            if (Auth::guard('staff')->check()) {
                Auth::guard('staff')->logout();
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('status', __('auth.reset_success'));
    }
}
