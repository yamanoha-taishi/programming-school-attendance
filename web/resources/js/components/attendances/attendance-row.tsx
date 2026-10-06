import type { AttendanceRowData } from '@/types/attendance';

export default function AttendanceRow({ row }: { row: AttendanceRowData }) {
    return (
        <li>
            {row.name} ({row.nameKana})
            {row.kind === 'regular'
                ? row.displayStatus
                : `${row.makeupType}・${row.absentDate}分`}
        </li>
    );
}
