<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * auth:guardian,staffのように複数ガードを指定した場合、Laravel標準の
     * 実装は常に「引数で渡された順番」で最初にcheck()が通ったガードを
     * デフォルトガードにする。このアプリでは常にguardianが先頭のため、
     * 同一ブラウザでguardian・staff両方にログイン中だと、あとからstaffで
     * ログインし直しても常にguardianが優先されてしまっていた。
     *
     * ログイン時にセッションへ記録している「直近でログインしたガード」
     * （active_guard）を、まだ実際にログイン中であれば最優先で試すことで、
     * 「スタッフとしてログインし直したらスタッフの画面になる」という
     * 直感的な挙動にする。active_guardが無効・未ログインの場合は元の
     * 順序（guardian→staff）にそのままフォールバックする。
     *
     * @param  array<int, string>  $guards
     * @return void
     */
    protected function authenticate($request, array $guards)
    {
        // 現状は全ルートがwebミドルウェアグループ経由でセッションが
        // 必ず開始済みだが、将来api系ガード（auth:sanctum等）でこの
        // authエイリアスが使われた場合、$request->session()は
        // セッション未開始でRuntimeExceptionを投げてしまう。hasSession()で
        // 確認してから使うことで、その場合は素の判定（想定通りの
        // 401/リダイレクト）にフォールバックする。
        $activeGuard = $request->hasSession() ? $request->session()->get('active_guard') : null;

        if (is_string($activeGuard) && in_array($activeGuard, $guards, true)) {
            $guards = array_values(array_unique([$activeGuard, ...$guards]));
        }

        // 親クラスの戻り値型はvoidであり、このメソッド自体も値を返す必要が
        // ないため、returnせずそのまま呼び出す。
        parent::authenticate($request, $guards);
    }
}
