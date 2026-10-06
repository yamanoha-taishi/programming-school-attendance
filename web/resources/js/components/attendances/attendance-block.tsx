import AttendanceRow from '@/components/attendances/attendance-row';
import type { AttendanceBlockData } from '@/types/attendance';

export default function AttendanceBlock({
    block,
}: {
    block: AttendanceBlockData;
}) {
    return (
        <section>
            <h2>{block.title}</h2>
            <p>
                {block.startTime}-{block.endTime}
            </p>
            <p>
                出席{block.summary.present}・欠席{block.summary.absent}・未記録
                {block.summary.unrecorded}
            </p>
            <ul>
                {block.rows.map((row) => (
                    <AttendanceRow
                        key={`${row.kind}-${row.attendanceId ?? row.studentId}`}
                        row={row}
                    />
                ))}
            </ul>
        </section>
    );
}
