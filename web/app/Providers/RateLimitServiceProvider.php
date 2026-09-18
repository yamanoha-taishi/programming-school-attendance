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
     * ログインのレート制限で使う生キー（memberCode単位／IP単位）を算出する。
     *
     * ログイン成功時にAuthenticatedSessionControllerがRateLimiter::clear()で
     * 該当バケットを解除する際にも同じロジックを使うことで、キーの算出方法が
     * 両者でずれないようにする。
     *
     * @return array{memberCode: string, ip: string}
     */
    public static function loginThrottleKeys(Request $request): array
    {
        // member_codeに配列などの非文字列が送られてくると、(string)
        // キャストであってもLaravelのエラーハンドラが「Array to string
        // conversion」をErrorExceptionへ変換するため、is_string()で
        // 安全に確認してから使う（配列の場合は空文字扱いにする）。
        $rawMemberCode = $request->input('member_code');
        $memberCode = Str::transliterate(Str::lower(is_string($rawMemberCode) ? $rawMemberCode : ''));

        return [
            'memberCode' => $memberCode.'|'.$request->ip(),
            'ip' => 'login-ip:'.$request->ip(),
        ];
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
            $keys = self::loginThrottleKeys($request);

            // 配列内の後に評価されたリミットのレスポンスヘッダ
            // （X-RateLimit-Remaining等）が上書きで残るため、より厳しい
            // memberCode単位のリミットを配列の最後に置き、クライアントには
            // 厳しい方の残り回数が見えるようにする。
            return [
                Limit::perMinute(20)->by($keys['ip']),
                Limit::perMinute(5)->by($keys['memberCode']),
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
