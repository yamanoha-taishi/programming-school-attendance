import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="パスワード再設定" />

            <Form {...email.form()} noValidate className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        {status && (
                            <div
                                role="status"
                                className="rounded-lg border border-status-attend bg-status-attend-bg px-3 py-2.5 text-sm text-status-attend-text"
                            >
                                {status}
                            </div>
                        )}

                        {errors.email && (
                            <div
                                id="forgot-password-error"
                                role="alert"
                                className="rounded-lg border border-status-absent bg-status-absent-bg px-3 py-2.5 text-sm text-status-absent-text"
                            >
                                <p>{errors.email}</p>
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="email">メールアドレス</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                aria-describedby={
                                    errors.email
                                        ? 'forgot-password-error'
                                        : undefined
                                }
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            再設定メールを送信
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            メールアドレスを登録していない方は、
                            <br />
                            運営にお問い合わせください。
                        </p>

                        <TextLink
                            href={login()}
                            className="mx-auto text-sm text-primary"
                        >
                            ログイン画面に戻る
                        </TextLink>
                    </div>
                )}
            </Form>
        </>
    );
}

ForgotPassword.layout = {
    title: 'パスワード再設定',
    description: '再設定用のリンクをメールでお送りします',
};
