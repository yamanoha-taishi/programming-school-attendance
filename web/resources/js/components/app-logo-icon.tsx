import { useId } from 'react';
import type { SVGAttributes } from 'react';

// アプリのロゴ：白い吹き出しの中に✓。出欠を連絡するサイトだとすぐ分かるようにする。
// ✓は吹き出しをくり抜いた形にして、fill-current で白く塗ったとき、✓の部分に下の色（primaryの四角など）が見えるようにする
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    // 同じ画面にロゴが2つ出ても、mask の id が重ならないようにする
    const maskId = useId();

    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <mask id={maskId}>
                    <rect width="24" height="24" fill="white" />
                    <path
                        d="M8 10.3l2.7 2.7 5.3-5.6"
                        fill="none"
                        stroke="black"
                        strokeWidth="2.6"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </mask>
            </defs>
            <path
                mask={`url(#${maskId})`}
                d="M4.5 3.5h15a2 2 0 0 1 2 2V15a2 2 0 0 1-2 2H11l-5 4v-4H4.5a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2z"
            />
        </svg>
    );
}
