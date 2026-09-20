<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfilePasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Guardian;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        // guardian・staffはメール確認の仕組み自体を持たないため、
        // mustVerifyEmailは画面側でも使っていない（フロント側の
        // 未検証メール表示ブロックも削除済み）。
        return Inertia::render('settings/profile', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        // guardian・staffはメール確認の仕組み自体を持たない
        // （MustVerifyEmailを実装せず、email_verified_atカラムも存在しない）ため、
        // その場合はこの処理をスキップする。email_verified_atはGuardian・Staffの
        // モデルには存在しない属性なので、直接プロパティ代入ではなくforceFill()で
        // 配列キーとして設定する（動的プロパティとしての型解決に依存しないため）。
        if ($user instanceof MustVerifyEmail && $user->isDirty('email')) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(ProfilePasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->password = $request->validated('password');
        $user->save();

        // 本人が意図して変更した操作のため、いま使っているこのセッションは
        // ログインしたまま維持し、同一アカウントの「他のセッション」だけを
        // 強制ログアウトさせる（他デバイスに残っていた古いパスワードの
        // セッションを締め出すため）。現在のセッション行は削除対象から
        // 除外しているため、パスワードリセット時に起きた「削除した自分の
        // セッション行が同じIDのまま復活する」問題は発生しない。
        $sessionColumn = $user instanceof Guardian ? 'guardian_id' : 'staff_id';

        DB::table('sessions')
            ->where($sessionColumn, $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // 退会（論理削除）時も、パスワード変更時と同様に他デバイスに
        // 残っている当該アカウントのセッションを破棄する。今アクセス中の
        // このセッションについては、この後regenerate(true)で新しい
        // セッションIDに切り替えるため、ここでは自セッションを除外せず
        // 全件削除してよい（削除後にregenerate(true)するため、
        // パスワードリセット時に起きた「削除した自分のセッション行が
        // 同じIDのまま復活する」問題は発生しない）。
        $guardName = $user instanceof Guardian ? 'guardian' : 'staff';
        $sessionColumn = $guardName === 'guardian' ? 'guardian_id' : 'staff_id';

        DB::table('sessions')
            ->where($sessionColumn, $user->id)
            ->delete();

        // Auth::logout()はデフォルトガード（web）を対象にしてしまい、
        // 実際にログイン中のguardian・staffガードには効かないため、
        // 削除対象アカウント自身のガードのみを明示的にログアウトする。
        // 以前はguardian・staff両方を無条件にログアウトしていたが、
        // 同一セッションで両ガードが同時に認証されている状態（別ガードで
        // 再ログインした場合）が正式なフローとしてサポートされたため、
        // 無関係な方のガードまで巻き添えでログアウトしてしまうのは
        // 意図しない副作用になる（例: スタッフとして再ログイン後に
        // スタッフ自身を退会させたら、無関係な保護者のログインまで
        // 消えてしまう）。
        Auth::guard($guardName)->logout();

        $user->delete();

        // session()->invalidate()はセッションデータ全体をclear()してから
        // 新しいIDへ切り替えるため、上と同じ理由で他方のガードのログイン
        // 状態まで消えてしまう。regenerate(true)であれば、セッション
        // データ自体は保持したまま（削除対象ガードのログイン情報は直前の
        // logout()で個別に消去済み）、旧セッション行だけをDBから破棄して
        // 新しいIDを発行できる（CSRFトークンの再発行も内部で行われる）。
        $request->session()->regenerate(true);

        return redirect('/');
    }
}
