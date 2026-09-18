<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // このメソッドはグローバルなwebミドルウェアグループの一部として、
        // ルート個別のauth:guardian,staffミドルウェアより先に実行される。
        // $request->user()はその時点のデフォルトガード（web、実際には
        // 誰もログインしない）しか見ないため常にnullになってしまう。
        // guardian・staffの各ガードを直接チェックすることで、ミドルウェアの
        // 実行順序に関係なく、実際にログイン中のユーザーを取得する。
        //
        // 同一ブラウザで両方のガードにログイン中の場合は、直近でログイン
        // した方（session('active_guard')）を優先する。auth:guardian,staff
        // ミドルウェア側の優先順位も App\Http\Middleware\Authenticate で
        // 同じactive_guardを参照するように揃えてあるため、認証必須ページ・
        // 不要ページのどちらでも一貫した結果になる。
        $activeGuard = $request->session()->get('active_guard');
        $activeGuardIsAuthenticated = in_array($activeGuard, ['guardian', 'staff'], true)
            && Auth::guard($activeGuard)->check();

        $user = match (true) {
            $activeGuardIsAuthenticated => Auth::guard($activeGuard)->user(),
            Auth::guard('guardian')->check() => Auth::guard('guardian')->user(),
            Auth::guard('staff')->check() => Auth::guard('staff')->user(),
            default => null,
        };

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Guardian・Staffモデルはpassword以外を隠していないため、
                // フルの属性を渡すとnote（運用側の特記事項）等の内部情報が
                // 全ページのHTMLに露出してしまう。フロントが実際に使う
                // 属性だけに絞って共有する。
                'user' => $user?->only(['id', 'name', 'email']),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
