import { formatMonthDay, subtractMinutes } from '@/lib/date-format';

// 補講予定のうち、30分前補講の数
function catchUpCount(makeupTypes: string[]): number {
    return makeupTypes.filter((type) => type === '30分前補講').length;
}

// 同じ生徒が同じ回に来る補講の名前。
// 30分前補講は数に応じて「30分前補講」「1時間前補講」、振替と一緒なら「振替＋30分前補講」のようにつなぐ
export function makeupLabel(makeupTypes: string[]): string {
    const count = catchUpCount(makeupTypes);
    const parts: string[] = [];

    if (makeupTypes.includes('振替')) {
        parts.push('振替');
    }

    if (count === 1) {
        parts.push('30分前補講');
    } else if (count === 2) {
        parts.push('1時間前補講');
    } else if (count > 2) {
        parts.push(`${count * 30}分前補講`);
    }

    return parts.join('＋');
}

// 実際に来てもらう時間。30分前補講1回分ごとに、授業の開始時刻から30分早める（30分前補講が無ければnull）
export function arrivalTime(
    startTime: string,
    makeupTypes: string[],
): string | null {
    const count = catchUpCount(makeupTypes);

    return count === 0 ? null : subtractMinutes(startTime, count * 30);
}

// 何日の欠席分か（"11/7・11/14欠席分"）
export function absentDatesLabel(absentDates: string[]): string {
    return `${absentDates.map(formatMonthDay).join('・')}欠席分`;
}
