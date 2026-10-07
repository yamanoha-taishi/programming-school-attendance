import { Head } from '@inertiajs/react';
import AttendanceBlock from '@/components/attendances/attendance-block';
import DateNav from '@/components/attendances/date-nav';
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
                <DateNav date={date} lessonDates={lessonDates} />
                <DaySummary summary={daySummary} />
            </header>
            {blocks.length === 0 && (
                <p className="px-3 py-10 text-center text-sm text-muted-foreground">
                    この日は授業がありません
                </p>
            )}
            {blocks.map((block) => (
                <AttendanceBlock key={block.sectionId} block={block} />
            ))}
        </>
    );
}
