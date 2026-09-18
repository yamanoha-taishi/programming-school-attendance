<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // 本番環境（実装ロードマップPhase 6）はRenderのWeb Service上で動かす
        // 予定で、外部からのリクエストは必ずRenderのリバースプロキシを経由する
        // （コンテナが直接インターネットからリクエストを受けることはない）。
        //
        // at: '*'（全プロキシを信頼）は誤りだった：Laravelの実装では
        // 「あらゆるIPを信頼済みプロキシとして扱う」設定になり、X-Forwarded-For
        // ヘッダの中身を検証する際に攻撃者が付けた偽のエントリまで「信頼済み
        // プロキシ」として扱われてしまう。結果として攻撃者が自由に指定した
        // 値がそのまま$request->ip()として採用されてしまい、ログインの
        // レート制限（member_code単位・IP単位とも）を素通りできてしまう
        // （Symfony Request::normalizeAndFilterClientIps()参照）。
        //
        // 代わりにat: ['REMOTE_ADDR']（「今まさに接続してきた直近の相手だけ」
        // を信頼する）を使う。Renderのような単一ホップのPaaSでは、コンテナに
        // 直接接続できるのはRenderの基盤だけなので、これで安全かつ正確に
        // 実クライアントIPを取得できる。
        //
        // X-Forwarded-Hostは信頼対象に含めない：Render自体のルーティングは
        // 本物のHostヘッダで行われるため信頼する必要がなく、信頼してしまうと
        // 攻撃者が偽装したホスト名をURL生成（パスワードリセットリンク等）に
        // 使われてしまう恐れがある。
        $middleware->trustProxies(
            at: ['REMOTE_ADDR'],
            headers: SymfonyRequest::HEADER_X_FORWARDED_FOR
                | SymfonyRequest::HEADER_X_FORWARDED_PORT
                | SymfonyRequest::HEADER_X_FORWARDED_PROTO,
        );

        // auth:guardian,staffの「複数ガードのうち最初にログイン中と判定
        // されたものをデフォルトガードにする」処理を、直近ログインした
        // ガード（session('active_guard')）優先に変更したもの。
        // 詳細はApp\Http\Middleware\Authenticateのコメント参照。
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
