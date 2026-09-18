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
        $user = Auth::guard('guardian')->user() ?? Auth::guard('staff')->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
