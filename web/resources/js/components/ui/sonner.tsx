import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            // 画面の下に固定しているタブバー（高さ約60px）に重ならないよう、その上に出す（スマホ幅でも同じ）
            offset={{ bottom: 76 }}
            mobileOffset={{ bottom: 76 }}
            // 白い画面の上で目立つよう、背景を文字の色（濃い紺）、文字を背景の色にする
            style={
                {
                    '--normal-bg': 'var(--foreground)',
                    '--normal-text': 'var(--background)',
                    '--normal-border': 'var(--foreground)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
