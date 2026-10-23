import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { SheetClose } from '@/components/ui/sheet';
import {
    addDays,
    addMonths,
    formatYearMonth,
    startOfMonth,
    startOfWeek,
} from '@/lib/date-format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/attendances';

const WEEKDAYS = ['月', '火', '水', '木', '金', '土', '日'];

// 週の行の並び（左に第何回の列、右に月〜日の7列）。休校の週の枠の中でも同じ並びにして、列をそろえる
const weekGridClass = 'grid grid-cols-[3rem_repeat(7,1fr)] items-center gap-1';

export default function DateCalendar({
    date,
    today,
    lessonDates,
    weekLessonNos,
    holidayWeeks,
}: {
    date: string; // 表示中の日付
    today: string;
    lessonDates: string[]; // 選べる授業日（新しい順）
    weekLessonNos: Record<string, number>; // 週ごとの第何回か（キーは月曜日の日付）
    holidayWeeks: Record<string, string>; // 休校の週と理由（キーは月曜日の日付）
}) {
    // 表示している月（その月の1日）。表示中の日付の月から始める
    const [month, setMonth] = useState(startOfMonth(date));

    const selectable = new Set(lessonDates);

    // 移動できる月の範囲：選べる日がある最初の月〜最後の月（表示中の日付の月は必ず含める）
    const firstMonth = startOfMonth(lessonDates.at(-1) ?? date);
    const lastMonth = startOfMonth(lessonDates[0] ?? date);
    const minMonth =
        firstMonth < startOfMonth(date) ? firstMonth : startOfMonth(date);
    const maxMonth =
        lastMonth > startOfMonth(date) ? lastMonth : startOfMonth(date);
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
                    onClick={() => setMonth(prevMonth)}
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
                    onClick={() => setMonth(nextMonth)}
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

            <div className="mt-1 space-y-1">
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
                                            {dayLabel(day)}
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
    isSelectable,
    isSelected,
    isToday,
}: {
    day: string;
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
                {dayLabel(day)}
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
                {dayLabel(day)}
                {todayDot}
            </Link>
        </SheetClose>
    );
}

// マスに出す日付。月の切れ目がわかるよう、1日だけ月も付ける（"2026-11-01" → "11/1"、"2026-11-02" → "2"）
function dayLabel(day: string): string {
    const [, month, date] = day.split('-').map(Number);

    return date === 1 ? `${month}/1` : String(date);
}
