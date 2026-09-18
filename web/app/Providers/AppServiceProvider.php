<?php

namespace App\Providers;

use App\Extensions\GuardAwareDatabaseSessionHandler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGuardAwareSessionDriver();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->letters()
                ->numbers()
                ->uncompromised()
            : null,
        );
    }

    /**
     * guardian・staffどちらのガードでログイン中かをsessionsテーブルの
     * guardian_id / staff_id に記録する、独自のセッションドライバを登録する。
     *
     * Session::extend()に渡すクロージャは、Store でラップしたものではなく
     * ハンドラ本体（SessionHandlerInterface）を返す必要がある。ラップは
     * SessionManager::callCustomCreator() 側が自動的に行う。
     */
    protected function configureGuardAwareSessionDriver(): void
    {
        Session::extend('guard-aware-database', function ($app) {
            return new GuardAwareDatabaseSessionHandler(
                $app['db']->connection($app['config']->get('session.connection')),
                $app['config']->get('session.table'),
                $app['config']->get('session.lifetime'),
                $app
            );
        });
    }
}
