import { Head } from '@inertiajs/react';

type Props = {
    date: string;
    blocks: {
        sectionId: number;
        title: string;
        startTime: string;
        endTime: string;
        rows: {
            kind: 'regular' | 'makeup';
            attendanceId: number | null;
            studentId: number;
            name: string;
            schoolClassName: string;
            lessonId: number;
            displayStatus?: string; // 通常の行だけ
            hasMakeup?: boolean; // 通常の行だけ
            makeupType?: string; // 「来る側」の行だけ
            absentDate?: string; // 「来る側」の行だけ
            makeupStatus?: string | null; // 「来る側」の行だけ
        }[];
    }[];
};

export default function AttendancesIndex({ date, blocks }: Props) {
    return (
        <>
            <Head title="出席記録・一覧" />
            <h1>出席記録・一覧</h1>
            <p>{date}</p>
            <pre>{JSON.stringify(blocks, null, 2)}</pre>
        </>
    );
}
