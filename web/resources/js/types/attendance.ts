export type AttendanceSummary = {
    present: number;
    absent: number;
    unrecorded: number;
};

export type AttendanceRowData = {
    kind: 'regular' | 'makeup';
    attendanceId: number | null;
    studentId: number;
    name: string;
    nameKana: string;
    schoolClassName: string;
    lessonId: number;
    displayStatus?: string; // 通常の行だけ
    status?: string | null; // 通常の行だけ
    hasMakeup?: boolean; // 通常の行だけ
    makeupType?: string; // 「来る側」の行だけ
    absentDate?: string; // 「来る側」の行だけ
    makeupStatus?: string | null; // 「来る側」の行だけ
};

export type AttendanceBlockData = {
    sectionId: number;
    title: string;
    startTime: string;
    endTime: string;
    summary: AttendanceSummary;
    rows: AttendanceRowData[];
};
