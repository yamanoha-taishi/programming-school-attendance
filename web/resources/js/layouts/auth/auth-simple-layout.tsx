import AppLogoIcon from '@/components/app-logo-icon';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <div className="flex flex-col items-center gap-3">
                            <div className="flex size-14 items-center justify-center rounded-[14px] bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-[30px] fill-current" />
                            </div>
                            <h1 className="text-center text-lg leading-snug font-bold text-foreground">
                                プログラミング教室
                                <br />
                                出席管理
                            </h1>
                        </div>

                        {(title || description) && (
                            <div className="space-y-2 text-center">
                                {title && (
                                    <h1 className="text-xl font-medium">
                                        {title}
                                    </h1>
                                )}
                                {description && (
                                    <p className="text-center text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
