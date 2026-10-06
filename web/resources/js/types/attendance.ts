export type AttendanceSummary = {
    present: number;
    absent: number;
    unrecorded: number;
};

// 両方の行に共通する項目
type AttendanceRowBase = {
    attendanceId: number | null;
    studentId: number;
    name: string;
    nameKana: string;
    schoolClassName: string;
    lessonId: number;
};

// 通常の行
export type RegularRowData = AttendanceRowBase & {
    kind: 'regular';
    displayStatus: string;
    status: string | null;
    hasMakeup: boolean;
};

// 「来る側」の行
export type MakeupRowData = AttendanceRowBase & {
    kind: 'makeup';
    makeupType: string;
    absentDate: string;
    makeupStatus: string | null;
};

export type AttendanceRowData = RegularRowData | MakeupRowData;

export type AttendanceBlockData = {
    sectionId: number;
    title: string;
    startTime: string;
    endTime: string;
    summary: AttendanceSummary;
    rows: AttendanceRowData[];
};
