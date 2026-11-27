import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';

// 管理画面（管理メニュー、保護者・生徒・スタッフの一覧・登録編集）の見出し。
// スクロールしても画面の上に固定する。出席記録・一覧のヘッダーと同じ白地・下に線
export default function PageHeader({
    title,
    backHref,
}: {
    title: string;
    // 「戻る」の行き先。渡さなければ「戻る」を出さない（管理メニュー）
    backHref?: string;
}) {
    return (
        // 高さを決めておき（「戻る」の44px＋下の線が入る48px）、「戻る」が無い画面でも同じ高さにする。
        // 画面を移ったときに、見出しが上下に動かないようにするため
        <header className="sticky top-0 z-10 flex h-12 items-center gap-1 border-b border-border bg-card px-3">
            {/* 「戻る」の見た目（文字だけか、枠を付けるか）は、一覧画面を作るときに決める */}
            {backHref !== undefined && (
                <Link
                    href={backHref}
                    className="-ml-2 flex h-11 items-center px-2 text-sm text-primary"
                >
                    <ChevronLeft className="size-4" />
                    戻る
                </Link>
            )}
            <h1 className="text-lg font-semibold">{title}</h1>
        </header>
    );
}
