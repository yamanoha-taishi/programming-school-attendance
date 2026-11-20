import { CircleDashed } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AttendanceSummary } from '@/types/attendance';

// その日の出欠サマリー。色は「記録してほしい未記録」だけに付け、出席・欠席は灰色＋小さな色の点にする
export default function DaySummary({
    summary,
}: {
    summary: AttendanceSummary;
}) {
    const grayClass =
        'inline-flex items-center gap-1 rounded-full bg-status-pending-bg px-2 py-0.5 font-semibold text-status-pending-text';

    return (
        <div className="mt-2 flex gap-2 text-xs">
            <span className={grayClass}>
                <span className="size-1.5 rounded-full bg-status-attend" />
                出席 {summary.present}
            </span>
            <span className={grayClass}>
                <span className="size-1.5 rounded-full bg-status-absent" />
                欠席 {summary.absent}
            </span>
            {/* 未記録が残っているときだけオレンジ。0なら灰色にして、やることが無いと分かるようにする */}
            <span
                className={cn(
                    grayClass,
                    summary.unrecorded > 0 &&
                        'bg-brand-accent-tint text-brand-accent-text',
                )}
            >
                {/* 行の「未記録」と同じ印。未記録が残っているときだけ付ける */}
                {summary.unrecorded > 0 && <CircleDashed className="size-3" />}
                未記録 {summary.unrecorded}
            </span>
        </div>
    );
}
