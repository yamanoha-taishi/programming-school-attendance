import StatusBadge from '@/components/attendances/status-badge';
import type { AttendanceRowData } from '@/types/attendance';

// 補講の種類ごとの色（淡い背景＋同系統の文字色）
const makeupTypeColors: Record<string, string> = {
    振替: 'bg-status-transfer-bg text-status-transfer-text',
    '30分前補講': 'bg-status-makeup-bg text-status-makeup',
};

// "2026-10-01" → "10/1"
function formatMonthDay(date: string): string {
    const [, month, day] = date.split('-');

    return `${Number(month)}/${Number(day)}`;
}

export default function AttendanceRow({ row }: { row: AttendanceRowData }) {
    return (
        <li className="flex items-center justify-between border-b border-border bg-card px-3 py-2.5">
            {/* 左：名前・カナ */}
            <div>
                <div className="flex items-center gap-1.5">
                    <span className="text-sm font-bold">{row.name}</span>
                    {/* 同じブロックの末尾に、この生徒の補講の行もある */}
                    {row.kind === 'regular' && row.hasMakeup && (
                        <span className="rounded-full bg-status-makeup-bg px-2 py-0.5 text-xs font-semibold text-status-makeup">
                            補講あり
                        </span>
                    )}
                </div>
                <div className="text-xs text-text-faint">{row.nameKana}</div>
            </div>

            {/* 右：通常の行は表示ステータス、「来る側」の行は補講の種類と欠席日 */}
            {row.kind === 'regular' ? (
                <StatusBadge status={row.displayStatus} />
            ) : (
                <span
                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${makeupTypeColors[row.makeupType] ?? ''}`}
                >
                    {row.makeupType}（{formatMonthDay(row.absentDate)}欠席分）
                </span>
            )}
        </li>
    );
}
