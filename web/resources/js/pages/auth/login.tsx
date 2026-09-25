import { Form, Head } from '@inertiajs/react';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { request } from '@/routes/password';

type Props = {
    status?: string;
};

export default function Login({ status }: Props) {
    return (
        <>
            <Head title="ログイン" />

            <Form
                {...AuthenticatedSessionController.store.form()}
                resetOnSuccess={['password']}
                noValidate
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            {(errors.member_code || errors.password) && (
                                <div className="rounded-lg border border-status-absent bg-status-absent-bg px-3 py-2.5 text-sm text-status-absent">
                                    {errors.member_code && (
                                        <p>{errors.member_code}</p>
                                    )}
                                    {errors.password && (
                                        <p>{errors.password}</p>
                                    )}
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="member_code">会員番号</Label>
                                <Input
                                    id="member_code"
                                    type="text"
                                    name="member_code"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    placeholder="例：0001"
                                    inputMode="numeric"
                                    maxLength={4}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">パスワード</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                tabIndex={3}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                ログイン
                            </Button>

                            <TextLink
                                href={request()}
                                className="mx-auto text-sm text-primary"
                                tabIndex={4}
                            >
                                パスワードをお忘れの方はこちら
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}
