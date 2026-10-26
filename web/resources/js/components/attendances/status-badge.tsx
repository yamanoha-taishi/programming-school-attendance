import { CircleDashed } from 'lucide-react';
import { cn } from '@/lib/utils';

// 出席ステータスのバッジ。色は「大事なところだけ」に付ける
// - 未記録：記録してほしいので、オレンジの塗り＋点線の丸のマークで目立たせる
// - 出席・欠席・済み：記録済みなので灰色にし、状態は小さな色の点で示す
const dotColors: Record<string, string> = {
    出席: 'bg-status-attend',
    欠席: 'bg-status-absent',
    // 振替済み・30分前補講済みは、補講に出席して対応が終わった状態なので、出席と同じ緑の点にする
    振替済み: 'bg-status-attend',
    '30分前補講済み': 'bg-status-attend',
};

export default function StatusBadge({ status }: { status: string }) {
    if (status === '未記録') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-brand-accent-tint px-2 py-0.5 text-xs font-semibold text-brand-accent-text">
                <CircleDashed className="size-3" />
                未記録
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-status-pending-bg px-2 py-0.5 text-xs font-semibold text-status-pending-text">
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    dotColors[status] ?? 'bg-status-pending',
                )}
            />
            {status}
        </span>
    );
}
