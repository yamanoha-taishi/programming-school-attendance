import { Head } from '@inertiajs/react';

export default function AttendancesIndex({ date }: { date: string }) {
    return (
        <>
            <Head title="出席記録・一覧" />
            <h1>出席記録・一覧</h1>
            <p>{date}</p>
        </>
    );
}
