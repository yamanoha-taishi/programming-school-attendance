<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            // member_codeは4桁（0001〜9999）と空間が狭く、この値単位のリミット
            // だけでは同一IPから会員番号を変えながら試すパスワードスプレー
            // 攻撃を防げない（1万通り試しても各バケットは5回/分に達しない）。
            // そのため、member_code単位のリミットに加えてIP単独のリミットも
            // 併用し、同一IPからの総試行回数自体に上限を設ける。
            //
            // member_codeに配列などの非文字列が送られてくると、(string)
            // キャストであってもLaravelのエラーハンドラが「Array to string
            // conversion」をErrorExceptionへ変換するため、is_string()で
            // 安全に確認してから使う（配列の場合は空文字扱いにする）。
            $rawMemberCode = $request->input('member_code');
            $memberCode = Str::transliterate(Str::lower(is_string($rawMemberCode) ? $rawMemberCode : ''));
            $throttleKey = $memberCode.'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $rawEmail = $request->input('email');
            $throttleKey = Str::transliterate(Str::lower(is_string($rawEmail) ? $rawEmail : '')).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
