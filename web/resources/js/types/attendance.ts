export type AttendanceSummary = {
    present: number;
    absent: number;
    unrecorded: number;
};

// 両方の行に共通する項目
type AttendanceRowBase = {
    studentId: number;
    name: string;
    nameKana: string;
    schoolClassName: string;
    lessonId: number;
};

// 通常の行
export type RegularRowData = AttendanceRowBase & {
    kind: 'regular';
    lessonNo: number; // 授業計画上の第何回か
    attendanceId: number | null; // まだ記録が無い生徒は null
    displayStatus: string;
    status: string | null;
    makeupType: string | null; // 欠席したときの補講予定（振替／30分前補講／未定／補講なし）
    makeupDate: string | null; // 補講先の日付（振替・30分前補講のときだけ）
    makeupStatus: string | null; // 補講先での出欠（未記録ならnull）
    hasMakeup: boolean;
};

// 「来る側」の行
export type MakeupRowData = AttendanceRowBase & {
    kind: 'makeup';
    attendanceId: number; // 元の欠席レコードが必ずある
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
    makeupCandidates: MakeupCandidate[]; // このブロックの生徒が欠席したときの補講先の候補
};

// 今後の休校（全体の休校だけ）を、期間ごとにまとめたもの
export type UpcomingHoliday = {
    reason: string; // 休校理由（年末年始など）
    from: string; // 期間の最初の日
    until: string; // 期間の最後の日
};

// 補講先（振替・30分前補講）の候補の授業回
export type MakeupCandidate = {
    lessonId: number;
    date: string;
    weekday: string;
    startTime: string;
    endTime: string;
    title: string; // 表示名（基礎クラスは「基礎クラス1組」、応用クラスは組を付けず「応用クラス」）
    lessonNo: number; // 授業計画上の第何回か（応用クラスは参考）
    sameContent: boolean; // 休んだ授業回と同じ回か（基礎クラスのみ。振替のときに強調するため。応用クラスは常にfalse）
};
