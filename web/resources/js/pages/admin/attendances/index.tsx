import { Head } from '@inertiajs/react';

type Props = {
    date: string;
    blocks: {
        sectionId: number;
        title: string;
        startTime: string;
        endTime: string;
        rows: {
            studentId: number;
            name: string;
            schoolClassName: string;
            lessonId: number;
            displayStatus: string;
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
