import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { SheetClose } from '@/components/ui/sheet';
import {
    addDays,
    addMonths,
    formatYearMonth,
    startOfMonth,
    startOfWeek,
} from '@/lib/date-format';
import { cn } from '@/lib/utils';
import { calendar as calendarRoute, index } from '@/routes/admin/attendances';
import type { CalendarData } from '@/types/attendance';

const WEEKDAYS = ['月', '火', '水', '木', '金', '土', '日'];

// 週の行の並び（左に第何回の列、右に月〜日の7列）。休校の週の枠の中でも同じ並びにして、列をそろえる
const weekGridClass = 'grid grid-cols-[3rem_repeat(7,1fr)] items-center gap-1';

export default function DateCalendar({
    date,
    today,
    firstSelectableDate,
    lastSelectableDate,
    calendar,
}: {
    date: string; // 表示中の日付
    today: string;
    firstSelectableDate: string | null; // 選べる授業日の最初
    lastSelectableDate: string | null; // 選べる授業日の最後
    calendar: CalendarData; // 表示中の日付の年度のカレンダー
}) {
    // 表示している月（その月の1日）。表示中の日付の月から始める
    const [month, setMonth] = useState(startOfMonth(date));

    // 読み込んだカレンダーのデータ。表示中の日付の年度の分は、最初から画面と一緒に受け取っている
    const [loaded, setLoaded] = useState<CalendarData>(calendar);

    // 表示している月を読み込み済みか。まだ読み込み中なら、選べる日・回数・休校の無い空のカレンダーを出す
    const isLoaded = loaded.months.includes(month.slice(0, 7));
    const selectable = new Set(isLoaded ? loaded.lessonDates : []);
    const weekLessonNos = isLoaded ? loaded.weekLessonNos : {};
    const holidayWeeks = isLoaded ? loaded.holidayWeeks : {};

    // 月を移る。その月をまだ読み込んでいなければ（ほかの年度の月なら）、その年度の分をサーバーから読み込む
    // （すべての授業回を一度に受け取ると年々増えるため、年度ごとに読み込む）
    function goToMonth(nextMonthStart: string) {
        setMonth(nextMonthStart);

        if (loaded.months.includes(nextMonthStart.slice(0, 7))) {
            return;
        }

        fetch(
            calendarRoute({ query: { month: nextMonthStart.slice(0, 7) } }).url,
            { headers: { Accept: 'application/json' } },
        )
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json() as Promise<CalendarData>;
            })
            .then((data) =>
                setLoaded((current) => mergeCalendar(current, data)),
            )
            .catch(() =>
                toast.error('カレンダーを読み込めませんでした', {
                    id: 'calendar-error',
                }),
            );
    }

    // 移動できる月の範囲：選べる日がある最初の月〜最後の月と、今年度（今日の年度の4月〜翌3月）の月。
    // 今年度の月は、選べる日が無くても、先の休校の週や第何回かを確かめられるよう表示できるようにする。
    // 表示中の日付の月は必ず含める
    const fiscalYearStart = startOfFiscalYear(today);
    const fiscalYearEnd = addMonths(fiscalYearStart, 11);
    const candidates = [
        startOfMonth(firstSelectableDate ?? date),
        startOfMonth(lastSelectableDate ?? date),
        startOfMonth(date),
        fiscalYearStart,
        fiscalYearEnd,
    ].sort();
    const minMonth = candidates[0];
    const maxMonth = candidates[candidates.length - 1];
    const prevMonth = addMonths(month, -1);
    const nextMonth = addMonths(month, 1);

    // その月の日を含む週（月曜日の日付）を、上から順に並べる
    const weeks: string[] = [];

    for (
        let weekStart = startOfWeek(month);
        weekStart < nextMonth;
        weekStart = addDays(weekStart, 7)
    ) {
        weeks.push(weekStart);
    }

    const arrowClass =
        'flex size-9 items-center justify-center rounded-lg border border-border text-muted-foreground disabled:opacity-40';

    return (
        <div>
            <div className="flex items-center justify-between">
                <button
                    type="button"
                    className={arrowClass}
                    disabled={prevMonth < minMonth}
                    onClick={() => goToMonth(prevMonth)}
                    aria-label="前の月"
                >
                    <ChevronLeft className="size-4" />
                </button>
                <span className="text-sm font-bold">
                    {formatYearMonth(month)}
                </span>
                <button
                    type="button"
                    className={arrowClass}
                    disabled={nextMonth > maxMonth}
                    onClick={() => goToMonth(nextMonth)}
                    aria-label="次の月"
                >
                    <ChevronRight className="size-4" />
                </button>
            </div>

            <div className={cn(weekGridClass, 'mt-2 text-center')}>
                <span />
                {WEEKDAYS.map((weekday) => (
                    <span
                        key={weekday}
                        className="py-1 text-[11px] text-muted-foreground"
                    >
                        {weekday}
                    </span>
                ))}
            </div>

            {/* 週の数（4〜6週）で高さが変わると、上の＜＞ボタンの位置が動いて連打できないため、
                6週分と休校の週の理由2行分の高さを、いつも取っておく
                （1週＝マス40px＋すき間4px、休校の週は理由の行の分だけ約21px高い） */}
            <div className="mt-1 min-h-[19rem] space-y-1">
                {weeks.map((weekStart) => {
                    const days = Array.from({ length: 7 }, (_, i) =>
                        addDays(weekStart, i),
                    );
                    const holidayReason = holidayWeeks[weekStart];

                    // 休校の週：1週まるごと点線の枠で囲み、日付と休みの理由を出す
                    if (holidayReason !== undefined) {
                        return (
                            <div
                                key={weekStart}
                                className="rounded-lg border-[1.5px] border-dashed border-text-faint pb-1.5"
                            >
                                <div className={weekGridClass}>
                                    <span className="pl-1.5 text-[11px] font-bold text-muted-foreground">
                                        休校
                                    </span>
                                    {days.map((day) => (
                                        <span
                                            key={day}
                                            className="flex h-9 items-center justify-center text-sm text-text-faint"
                                        >
                                            {dayLabel(day, weeks[0])}
                                        </span>
                                    ))}
                                </div>
                                <p className="text-center text-xs font-bold text-muted-foreground">
                                    {holidayReason}
                                </p>
                            </div>
                        );
                    }

                    const lessonNo = weekLessonNos[weekStart];
                    // 選べる日が1日も無い週（先の週など）は、回数を薄く出す
                    const hasSelectable = days.some((day) =>
                        selectable.has(day),
                    );

                    return (
                        <div key={weekStart} className={weekGridClass}>
                            <span
                                className={cn(
                                    'text-[11px] whitespace-nowrap',
                                    hasSelectable
                                        ? 'font-bold text-muted-foreground'
                                        : 'text-text-faint',
                                    weekStart === startOfWeek(date) &&
                                        'text-primary',
                                )}
                            >
                                {lessonNo !== undefined && `第${lessonNo}回`}
                            </span>
                            {days.map((day) => (
                                <DayCell
                                    key={day}
                                    day={day}
                                    label={dayLabel(day, weeks[0])}
                                    isSelectable={selectable.has(day)}
                                    isSelected={day === date}
                                    isToday={day === today}
                                />
                            ))}
                        </div>
                    );
                })}
            </div>

            {/* 凡例 */}
            <div className="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-muted-foreground">
                <span className="flex items-center gap-1">
                    <span className="size-3 rounded-sm bg-primary-tint" />
                    選べる日
                </span>
                <span className="flex items-center gap-1">
                    <span className="size-3 rounded-sm border-[1.5px] border-dashed border-text-faint" />
                    休校の週
                </span>
                <span className="flex items-center gap-1">
                    <span className="size-3 rounded-sm bg-primary" />
                    表示中
                </span>
                <span className="flex items-center gap-1">
                    <span className="size-1 rounded-full bg-primary" />
                    今日
                </span>
            </div>
        </div>
    );
}

// 日付のマス。選べる日は押すとその日付に切り替えてモーダルを閉じる
function DayCell({
    day,
    label,
    isSelectable,
    isSelected,
    isToday,
}: {
    day: string;
    label: string; // マスに出す日付（dayLabel）
    isSelectable: boolean;
    isSelected: boolean;
    isToday: boolean;
}) {
    const className = cn(
        'relative flex h-10 items-center justify-center rounded-lg text-sm',
        isSelectable
            ? 'bg-primary-tint font-bold text-primary'
            : 'text-text-faint',
        isSelected && 'bg-primary font-bold text-primary-foreground',
    );

    // 今日の印（マスの下の小さな点）
    const todayDot = isToday && (
        <span
            className={cn(
                'absolute bottom-1 size-1 rounded-full',
                isSelected ? 'bg-primary-foreground' : 'bg-primary',
            )}
        />
    );

    if (!isSelectable) {
        return (
            <span className={className}>
                {label}
                {todayDot}
            </span>
        );
    }

    return (
        <SheetClose asChild>
            <Link
                href={index({ query: { date: day } }).url}
                className={className}
                aria-current={isSelected ? 'date' : undefined}
            >
                {label}
                {todayDot}
            </Link>
        </SheetClose>
    );
}

// 読み込んだカレンダーのデータに、新しく読み込んだ年度の分を足す（同じものは1つにまとめる）
function mergeCalendar(
    current: CalendarData,
    added: CalendarData,
): CalendarData {
    return {
        months: [...new Set([...current.months, ...added.months])],
        lessonDates: [
            ...new Set([...current.lessonDates, ...added.lessonDates]),
        ],
        weekLessonNos: { ...current.weekLessonNos, ...added.weekLessonNos },
        holidayWeeks: { ...current.holidayWeeks, ...added.holidayWeeks },
    };
}

// その日の年度の始まり（4月1日）。"2026-11-22" → "2026-04-01"、"2027-02-10" → "2026-04-01"
function startOfFiscalYear(date: string): string {
    const year = Number(date.slice(0, 4));
    const month = Number(date.slice(5, 7));

    return `${month >= 4 ? year : year - 1}-04-01`;
}

// マスに出す日付。何月の日かわかるよう、カレンダーの最初のマスと、月が変わる1日だけ月も付ける
// （"2026-10-26"が最初のマス → "10/26"、"2026-11-01" → "11/1"、"2026-11-02" → "2"）
function dayLabel(day: string, firstDay: string): string {
    const [, month, date] = day.split('-').map(Number);

    return date === 1 || day === firstDay ? `${month}/${date}` : String(date);
}
