import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight, Info } from 'lucide-react';
import DateCalendar from '@/components/attendances/date-calendar';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { formatDateLabel, formatMonthDay } from '@/lib/date-format';
import { index } from '@/routes/admin/attendances';
import type { CalendarData, UpcomingHoliday } from '@/types/attendance';

export default function DateNav({
    date,
    today,
    prevDate,
    nextDate,
    firstSelectableDate,
    lastSelectableDate,
    calendar,
    advanceUntil,
    advanceWeeks,
    lessonNos,
    upcomingHolidays,
}: {
    date: string;
    today: string;
    prevDate: string | null; // ◀で移る、前の選べる授業日
    nextDate: string | null; // ▶で移る、次の選べる授業日
    firstSelectableDate: string | null; // 選べる授業日の最初
    lastSelectableDate: string | null; // 選べる授業日の最後
    calendar: CalendarData; // 表示中の日付の年度のカレンダー
    advanceUntil: string; // 事前に登録できる期限の目安（今日から5週間後）
    advanceWeeks: number; // 事前に登録できる期限が、今日から何週間後か
    lessonNos: number[]; // 授業計画上の第何回か（ふつうは1つ）
    upcomingHolidays: UpcomingHoliday[]; // 今後の休校（近い順に最大3件）
}) {
    // ◀ ▶：日付を移動する操作なので、日付のボタン・「今日」と同じ青にそろえる
    const arrowClass =
        'flex h-8 items-center rounded-md border border-primary bg-card px-2 text-primary';

    return (
        <div className="grid grid-cols-[1fr_auto_1fr] items-center">
            {/* 左：授業計画上の第何回か（授業がない日は出さない）。
                押せるボタンと区別するため、枠や背景は付けず、左に濃い灰色の線を付けて「意味のある情報」として目立たせる（青は操作に使うため使わない） */}
            {lessonNos.length > 0 && (
                <div className="border-l-3 border-foreground pl-1.5 text-sm leading-tight font-bold">
                    第{lessonNos.join('・')}回
                </div>
            )}

            <div className="col-start-2 flex items-center gap-2.5">
                {prevDate ? (
                    <Link
                        href={index({ query: { date: prevDate } }).url}
                        className={arrowClass}
                        aria-label="前の授業日"
                    >
                        <ChevronLeft className="size-4" />
                    </Link>
                ) : (
                    <span className={`${arrowClass} opacity-40`}>
                        <ChevronLeft className="size-4" />
                    </span>
                )}

                <Sheet>
                    {/* 日付ボタン：タップすると日付の一覧を開く */}
                    <SheetTrigger className="flex w-30 items-center justify-center gap-1 rounded-full border border-primary bg-primary-tint px-2 py-1 text-sm font-bold text-primary">
                        {formatDateLabel(date)}
                        <ChevronDown className="size-4" />
                    </SheetTrigger>

                    <SheetContent
                        side="bottom"
                        aria-describedby={undefined}
                        className="max-h-[90dvh] overflow-y-auto rounded-t-2xl bg-card p-4"
                    >
                        <SheetTitle className="text-sm font-bold">
                            日付を選択
                        </SheetTitle>

                        {/* 今後の休校：次に休みになる週を確かめられるようにする */}
                        {upcomingHolidays.length > 0 && (
                            <div className="rounded-lg bg-background px-3 py-2 text-xs">
                                <p className="mb-1 font-bold text-muted-foreground">
                                    今後の休校
                                </p>
                                <ul className="space-y-0.5">
                                    {upcomingHolidays.map((holiday) => (
                                        <li
                                            key={holiday.from}
                                            className="flex gap-2"
                                        >
                                            <span className="w-16 shrink-0 text-muted-foreground">
                                                {holiday.reason}
                                            </span>
                                            <span>
                                                {formatDateLabel(holiday.from)}
                                                {holiday.until !==
                                                    holiday.from &&
                                                    `〜${formatDateLabel(holiday.until)}`}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* 事前に登録できる期限の目安。それより先でも、記録がある日は選べる */}
                        <p className="flex items-start gap-1.5 rounded-lg bg-background px-3 py-2 text-xs text-foreground">
                            <Info className="mt-px size-3.5 shrink-0" />
                            事前に登録できるのは、{advanceWeeks}週間後（
                            {formatMonthDay(advanceUntil)}）までです
                        </p>

                        {/* カレンダー。モーダルを閉じると消え、開くたびに表示中の日付の月から始まる */}
                        <DateCalendar
                            date={date}
                            today={today}
                            firstSelectableDate={firstSelectableDate}
                            lastSelectableDate={lastSelectableDate}
                            calendar={calendar}
                        />

                        <SheetClose className="h-11 rounded-lg border border-border text-sm font-bold">
                            キャンセル
                        </SheetClose>
                    </SheetContent>
                </Sheet>

                {nextDate ? (
                    <Link
                        href={index({ query: { date: nextDate } }).url}
                        className={arrowClass}
                        aria-label="次の授業日"
                    >
                        <ChevronRight className="size-4" />
                    </Link>
                ) : (
                    <span className={`${arrowClass} opacity-40`}>
                        <ChevronRight className="size-4" />
                    </span>
                )}
            </div>

            <Link
                href={index().url}
                className="justify-self-end rounded-full border border-primary bg-card px-2.5 py-1 text-xs text-primary"
            >
                今日
            </Link>
        </div>
    );
}
