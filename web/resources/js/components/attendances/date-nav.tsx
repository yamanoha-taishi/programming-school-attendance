import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { formatDateLabel } from '@/lib/date-format';
import { index } from '@/routes/admin/attendances';
import type { UpcomingHoliday } from '@/types/attendance';

export default function DateNav({
    date,
    lessonDates,
    lessonNos,
    upcomingHolidays,
}: {
    date: string;
    lessonDates: string[]; // 今日以前の授業日と、記録が登録されている未来の授業日（新しい順）
    lessonNos: number[]; // 授業計画上の第何回か（ふつうは1つ）
    upcomingHolidays: UpcomingHoliday[]; // 今後の休校（近い順に最大3件）
}) {
    // 前の授業日：今の日付より古い日付のうち、いちばん新しいもの
    const prevDate = lessonDates.find((d) => d < date);
    // 次の授業日：今の日付より新しい日付のうち、いちばん古いもの
    const nextDate = lessonDates.filter((d) => d > date).at(-1);

    const arrowClass =
        'flex h-8 items-center rounded-md border border-border px-2 text-muted-foreground';

    return (
        <div className="grid grid-cols-[1fr_auto_1fr] items-center">
            {/* 左：授業計画上の第何回か（授業がない日は出さない）。
                押せるボタンと区別するため、枠や背景は付けず、左に色の線を付けて「意味のある情報」として目立たせる */}
            {lessonNos.length > 0 && (
                <div className="border-l-3 border-primary pl-1.5 text-sm leading-tight font-bold">
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
                        className="rounded-t-2xl bg-card p-4"
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

                        {/* 授業日の一覧（新しい順。記録が登録されている未来の日付も含む）。多いのでスクロールさせる */}
                        <ul className="max-h-[50dvh] space-y-2 overflow-y-auto">
                            {lessonDates.map((lessonDate) => (
                                <li key={lessonDate}>
                                    {/* 選ぶと、その日付に切り替えてモーダルを閉じる */}
                                    <SheetClose asChild>
                                        <Link
                                            href={
                                                index({
                                                    query: { date: lessonDate },
                                                }).url
                                            }
                                            className={`block rounded-lg border px-3 py-2.5 text-sm ${
                                                lessonDate === date
                                                    ? 'border-primary bg-primary-tint font-bold text-primary'
                                                    : 'border-border'
                                            }`}
                                        >
                                            {formatDateLabel(lessonDate)}
                                        </Link>
                                    </SheetClose>
                                </li>
                            ))}
                        </ul>

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
