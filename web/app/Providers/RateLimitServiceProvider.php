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

            // 上限を超えたときは429のエラーページではなく、ほかのログイン
            // エラーと同じくエラーメッセージ付きでログイン画面に戻す
            // （Inertiaは通常のHTMLページを受け取るとモーダルで表示してしまうため）。
            $tooManyAttempts = fn (Request $request, array $headers) => back()->withErrors([
                'member_code' => __('auth.throttle', ['seconds' => $headers['Retry-After']]),
            ]);

            // ThrottleRequestsのaddHeaders()は「既存のX-RateLimit-Remaining
            // より小さい場合のみ上書きする」実装のため、実際にはどちらを
            // 先に置いても基本的に厳しい方（残り回数が少ない方）の値が
            // 残る。ただし両者の残り回数がたまたま同値になった場合のみ
            // 後に評価された方が勝つため、より厳しいmemberCode単位を
            // 配列の最後に置き、その同値ケースでも厳しい方が表示される
            // ようにしている。
            return [
                Limit::perMinute(20)->by($keys['ip'])->response($tooManyAttempts),
                Limit::perMinute(5)->by($keys['memberCode'])->response($tooManyAttempts),
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
