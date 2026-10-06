import { Head } from '@inertiajs/react';
import AttendanceBlock from '@/components/attendances/attendance-block';
import DaySummary from '@/components/attendances/day-summary';
import type {
    AttendanceBlockData,
    AttendanceSummary,
} from '@/types/attendance';

type Props = {
    date: string;
    daySummary: AttendanceSummary;
    blocks: AttendanceBlockData[];
};

export default function AttendancesIndex({ date, daySummary, blocks }: Props) {
    return (
        <>
            <Head title="出席記録・一覧" />
            <h1>出席記録・一覧</h1>
            <p>{date}</p>
            <DaySummary summary={daySummary} />
            {blocks.map((block) => (
                <AttendanceBlock key={block.sectionId} block={block} />
            ))}
        </>
    );
}
