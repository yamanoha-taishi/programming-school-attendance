import { Head } from '@inertiajs/react';
import AttendanceBlock from '@/components/attendances/attendance-block';
import DaySummary from '@/components/attendances/day-summary';
import type {
    AttendanceBlockData,
    AttendanceSummary,
} from '@/types/attendance';

type Props = {
    date: string;
    lessonDates: string[]; // 今日以前で授業がある日付（新しい順）
    daySummary: AttendanceSummary;
    blocks: AttendanceBlockData[];
};

export default function AttendancesIndex({
    date,
    lessonDates,
    daySummary,
    blocks,
}: Props) {
    return (
        <>
            <Head title="出席記録・一覧" />
            <header className="border-b border-border bg-card px-3 py-2.5">
                <p className="text-sm font-bold">{date}</p>
                <DaySummary summary={daySummary} />
            </header>
            {blocks.map((block) => (
                <AttendanceBlock key={block.sectionId} block={block} />
            ))}
        </>
    );
}
