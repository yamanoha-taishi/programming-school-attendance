import AttendanceRow from '@/components/attendances/attendance-row';
import type { AttendanceBlockData } from '@/types/attendance';

export default function AttendanceBlock({
    block,
    date,
    isFuture,
}: {
    block: AttendanceBlockData;
    date: string; // 表示している日付
    isFuture: boolean; // まだ来ていない日か
}) {
    return (
        <section>
            {/* 見出し（クラス名・時間・ブロックの出欠サマリー） */}
            <div className="border-y border-border bg-muted px-3 py-1.5">
                <div className="flex justify-between text-xs font-bold">
                    <span>{block.title}</span>
                    <span className="font-normal text-muted-foreground">
                        {block.startTime}-{block.endTime}
                    </span>
                </div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                    出席{block.summary.present} ・ 欠席{block.summary.absent} ・{' '}
                    <b className="text-foreground">
                        未記録{block.summary.unrecorded}
                    </b>
                </div>
            </div>

            {/* 生徒の行 */}
            <ul>
                {block.rows.map((row) => (
                    <AttendanceRow
                        key={
                            row.kind === 'regular'
                                ? `regular-${row.studentId}`
                                : `makeup-${row.attendanceId}`
                        }
                        row={row}
                        makeupCandidates={block.makeupCandidates}
                        date={date}
                        isFuture={isFuture}
                    />
                ))}
            </ul>
        </section>
    );
}
