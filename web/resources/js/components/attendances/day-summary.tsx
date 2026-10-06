import type { AttendanceSummary } from '@/types/attendance';

export default function DaySummary({
    summary,
}: {
    summary: AttendanceSummary;
}) {
    return (
        <section>
            <p>
                出席{summary.present}・欠席{summary.absent}・未記録
                {summary.unrecorded}
            </p>
        </section>
    );
}
