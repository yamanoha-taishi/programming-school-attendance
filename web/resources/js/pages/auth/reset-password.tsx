import { Form, Head } from '@inertiajs/react';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { request, update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="パスワード再設定" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                noValidate
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => {
                    const hasError = Boolean(
                        errors.email ||
                        errors.password ||
                        errors.password_confirmation,
                    );

                    return (
                        <div className="grid gap-6">
                            {hasError && (
                                <div
                                    id="reset-password-error"
                                    role="alert"
                                    className="rounded-lg border border-status-absent bg-status-absent-bg px-3 py-2.5 text-sm text-status-absent-text"
                                >
                                    {errors.email && <p>{errors.email}</p>}
                                    {errors.password && (
                                        <p>{errors.password}</p>
                                    )}
                                    {errors.password_confirmation && (
                                        <p>{errors.password_confirmation}</p>
                                    )}
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="email">メールアドレス</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={email}
                                    readOnly
                                    className="bg-muted text-muted-foreground"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    新しいパスワード
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    aria-describedby={
                                        hasError
                                            ? 'password-hint reset-password-error'
                                            : 'password-hint'
                                    }
                                />
                                <p
                                    id="password-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    8文字以上で、英字と数字を含めてください
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    新しいパスワード（確認用）
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    aria-describedby={
                                        hasError
                                            ? 'reset-password-error'
                                            : undefined
                                    }
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="reset-password-button"
                            >
                                {processing && <Spinner />}
                                再設定する
                            </Button>

                            {errors.email && (
                                <TextLink
                                    href={request()}
                                    className="mx-auto text-sm text-primary"
                                >
                                    再設定メールをもう一度送る
                                </TextLink>
                            )}
                        </div>
                    );
                }}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'パスワード再設定',
};
