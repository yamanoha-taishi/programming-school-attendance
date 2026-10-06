import type { AttendanceSummary } from '@/types/attendance';

export default function DaySummary({
    summary,
}: {
    summary: AttendanceSummary;
}) {
    return (
        <div className="mt-2 flex gap-2 text-xs">
            <span className="rounded-full bg-status-attend-bg px-2 py-0.5 font-semibold text-status-attend-text">
                出席 {summary.present}
            </span>
            <span className="rounded-full bg-status-absent-bg px-2 py-0.5 font-semibold text-status-absent-text">
                欠席 {summary.absent}
            </span>
            <span className="rounded-full bg-brand-accent-tint px-2 py-0.5 font-semibold text-brand-accent-hover">
                未記録 {summary.unrecorded}
            </span>
        </div>
    );
}
