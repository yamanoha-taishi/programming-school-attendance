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
    makeupLessonId: number | null; // 補講先の授業回のID（振替・30分前補講のときだけ）
    makeupSessionKey: string | null; // 補講先の回を表すキー（同じ回を選び直したかの判定に使う）
    makeupStatus: string | null; // 補講先での出欠（未記録ならnull）
    recordedBy: string | null; // 記録したスタッフの名前（スタッフがまだ対応していなければnull）
    isNotice: boolean; // 保護者からの事前連絡で、スタッフがまだ対応していない記録か
    note: string | null; // 保護者からの連絡事項
    // 補講先の回に、同じ生徒が入れている補講予定（この欠席の分も含む。30分前補講が2つなら1時間前補講）
    makeupGroupTypes: string[];
    otherMakeups: OtherMakeup[]; // ほかの欠席分の補講予定（補講先を選ぶモーダルで使う）
    catchUp: MakeupGroupData | null; // 自分のクラスのこの回に補講でも来る場合の、補講の情報
};

// 同じ生徒のほかの欠席分の補講予定
export type OtherMakeup = {
    sessionKey: string; // 補講先の回を表すキー（MakeupCandidateのsessionKeyと比べる）
    makeupType: string;
    absentDate: string; // 何日の欠席分か
};

// 同じ生徒が同じ回に来る補講の情報。1つ、または2つ（1時間前補講・振替＋30分前補講）をまとめたもの
export type MakeupGroupData = {
    attendanceId: number; // 元の欠席記録のID（補講先での出欠を記録するときに使う。同じ回の補講はまとめて記録される）
    makeupTypes: string[]; // 補講予定（振替が先）
    absences: { absentDate: string; note: string | null }[]; // 何日の欠席分か（古い順）と、その日の連絡事項
    makeupStatus: string | null; // 補講先での出欠（未記録ならnull）
};

// 「来る側」の行（1人1行。同じ生徒の補講が2つあれば1行にまとめる）
export type MakeupRowData = AttendanceRowBase &
    MakeupGroupData & {
        kind: 'makeup';
    };

export type AttendanceRowData = RegularRowData | MakeupRowData;

export type AttendanceBlockData = {
    sectionId: number;
    title: string;
    startTime: string;
    endTime: string;
    rows: RegularRowData[]; // このブロックに所属する生徒の行
    makeupRows: MakeupRowData[]; // ほかの回から補講で来る生徒の行
    summary: AttendanceSummary; // 所属する生徒の出欠
    makeupSummary: AttendanceSummary; // ほかの回から補講で来る生徒の、補講先での出欠
    makeupCandidates: MakeupCandidate[]; // このブロックの生徒が欠席したときの補講先の候補
};

// 日付選択のカレンダーのデータ。1年度分（4月〜翌3月）の月を含む週の範囲
export type CalendarData = {
    months: string[]; // 読み込んだ月（"2026-04" の形）。1年度分（4月〜翌3月）
    lessonDates: string[]; // 選べる授業日（古い順）
    weekLessonNos: Record<string, number>; // 週ごとの第何回か（キーは月曜日の日付）
    holidayWeeks: Record<string, string>; // 休校の週と理由（キーは月曜日の日付）
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
    sessionKey: string; // 補講先の回を表すキー（同じ日・同じ授業実施単位なら同じ）
    date: string;
    weekday: string;
    startTime: string;
    endTime: string;
    title: string; // 表示名（基礎クラスは「基礎クラス1組」、応用クラスは組を付けず「応用クラス」）
    lessonNo: number; // 授業計画上の第何回か（応用クラスは参考）
    sameContent: boolean; // 休んだ授業回と同じ回か（基礎クラスのみ。振替のときに強調するため。応用クラスは常にfalse）
};
