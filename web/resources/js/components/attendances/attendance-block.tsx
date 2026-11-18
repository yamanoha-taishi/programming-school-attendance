import { CircleDashed } from 'lucide-react';
import AttendanceRow from '@/components/attendances/attendance-row';
import { cn } from '@/lib/utils';
import type {
    AttendanceBlockData,
    AttendanceSummary,
} from '@/types/attendance';

export default function AttendanceBlock({
    block,
    date,
    isFuture,
}: {
    block: AttendanceBlockData;
    date: string; // 表示している日付
    isFuture: boolean; // まだ来ていない日か
}) {
    const hasMakeupRows = block.makeupRows.length > 0;

    return (
        <section>
            {/* 見出し（クラス名・時間と、このブロックに所属する生徒の出欠サマリー）。
                灰色の帯は、クラスの始まりにだけ使う */}
            <div className="border-y border-border bg-muted px-3 py-1.5">
                <div className="flex justify-between text-xs font-bold">
                    <span>{block.title}</span>
                    <span className="font-normal text-muted-foreground">
                        {block.startTime}-{block.endTime}
                    </span>
                </div>
                <BlockSummary summary={block.summary} className="mt-0.5" />
            </div>

            {/* このブロックに所属する生徒。
                補講で来る生徒がいるときは、最後の行の下の線を消し、補講の小見出しの点線で区切る */}
            <ul className={cn(hasMakeupRows && '[&>li:last-child]:border-b-0')}>
                {block.rows.map((row) => (
                    <AttendanceRow
                        key={`regular-${row.studentId}`}
                        row={row}
                        makeupCandidates={block.makeupCandidates}
                        date={date}
                        startTime={block.startTime}
                        isFuture={isFuture}
                    />
                ))}
            </ul>

            {/* ほかの回から補講で来る生徒。同じクラスの中の一部に見えるよう、帯にせず、
                点線の区切りと白地の小見出し（「補講」のラベル＋出欠サマリー）で分けて、別に数える */}
            {hasMakeupRows && (
                <>
                    <div className="flex items-center gap-1.5 border-t border-dashed border-text-faint/50 bg-card px-3 pt-2 pb-1">
                        <span className="rounded-full border border-border bg-card px-1.5 text-[10px] font-bold text-muted-foreground">
                            補講
                        </span>
                        <BlockSummary summary={block.makeupSummary} />
                    </div>
                    <ul>
                        {block.makeupRows.map((row) => (
                            <AttendanceRow
                                key={`makeup-${row.attendanceId}`}
                                row={row}
                                makeupCandidates={block.makeupCandidates}
                                date={date}
                                startTime={block.startTime}
                                isFuture={isFuture}
                            />
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}

// ブロックの出欠サマリー（「● 出席 4 ● 欠席 2 未記録 0」）。
// 出席・欠席は行のバッジと同じ色の点を付け、数字を太字にする。未記録は残っているときだけオレンジにする
function BlockSummary({
    summary,
    className,
}: {
    summary: AttendanceSummary;
    className?: string;
}) {
    const hasUnrecorded = summary.unrecorded > 0;

    return (
        <div
            className={cn(
                'flex items-center gap-3 text-xs whitespace-nowrap text-muted-foreground',
                className,
            )}
        >
            <span className="flex items-center gap-1">
                <span className="size-1.5 rounded-full bg-status-attend" />
                出席<b className="text-foreground">{summary.present}</b>
            </span>
            <span className="flex items-center gap-1">
                <span className="size-1.5 rounded-full bg-status-absent" />
                欠席<b className="text-foreground">{summary.absent}</b>
            </span>
            <span
                className={cn(
                    'flex items-center gap-1',
                    hasUnrecorded && 'font-bold text-brand-accent-text',
                )}
            >
                {hasUnrecorded && <CircleDashed className="size-3" />}
                未記録
                <b className={cn(!hasUnrecorded && 'text-foreground')}>
                    {summary.unrecorded}
                </b>
            </span>
        </div>
    );
}
