// 日付（"2026-10-06" の形の文字列）を画面に表示するための関数

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

// "2026-10-06" → Date。new Date("2026-10-06") だと世界標準時として読まれて日付がずれることがあるため、年・月・日に分けて作る
function toDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
}

// Date → "2026-10-06"
function toDateString(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

// "2026-10-06" → "10/6(火)"
export function formatDateLabel(date: string): string {
    const d = toDate(date);

    return `${d.getMonth() + 1}/${d.getDate()}(${WEEKDAYS[d.getDay()]})`;
}

// "2026-10-01" → "10/1"
export function formatMonthDay(date: string): string {
    const d = toDate(date);

    return `${d.getMonth() + 1}/${d.getDate()}`;
}

// その日を含む週の月曜日。"2026-10-15"（木）→ "2026-10-12"（月）
export function startOfWeek(date: string): string {
    const d = toDate(date);

    // getDay()は日曜=0〜土曜=6。月曜を週の始まりにするため、月曜から何日目か（月=0〜日=6）に直して、その分さかのぼる
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7));

    return toDateString(d);
}

// 日数を足す。"2026-10-12" に 6 → "2026-10-18"
export function addDays(date: string, days: number): string {
    const d = toDate(date);

    d.setDate(d.getDate() + days);

    return toDateString(d);
}

// その日を含む月の1日。"2026-10-22" → "2026-10-01"
export function startOfMonth(date: string): string {
    return `${date.slice(0, 7)}-01`;
}

// 月を足す（結果はその月の1日）。"2026-10-01" に 1 → "2026-11-01"
export function addMonths(date: string, months: number): string {
    const d = toDate(startOfMonth(date));

    d.setMonth(d.getMonth() + months);

    return toDateString(d);
}

// "2026-10-01" → "2026年10月"
export function formatYearMonth(date: string): string {
    const d = toDate(date);

    return `${d.getFullYear()}年${d.getMonth() + 1}月`;
}

// 時刻から分を引く。"15:00" から 30 → "14:30"
export function subtractMinutes(time: string, minutes: number): string {
    const [hour, minute] = time.split(':').map(Number);
    const total = hour * 60 + minute - minutes;

    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}
