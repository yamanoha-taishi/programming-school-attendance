import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { index } from '@/routes/admin/attendances';

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

// "2026-10-06" → "10/6(火)"
function formatDateLabel(date: string): string {
    const [year, month, day] = date.split('-').map(Number);
    const weekday = WEEKDAYS[new Date(year, month - 1, day).getDay()];

    return `${month}/${day}(${weekday})`;
}

export default function DateNav({
    date,
    lessonDates,
}: {
    date: string;
    lessonDates: string[];
}) {
    // 前の授業日：今の日付より古い日付のうち、いちばん新しいもの
    const prevDate = lessonDates.find((d) => d < date);
    // 次の授業日：今の日付より新しい日付のうち、いちばん古いもの
    const nextDate = lessonDates.filter((d) => d > date).at(-1);

    const arrowClass =
        'flex h-8 items-center rounded-md border border-border px-2 text-muted-foreground';

    return (
        <div className="grid grid-cols-[1fr_auto_1fr] items-center">
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
                        className="rounded-t-2xl p-4"
                    >
                        <SheetTitle className="text-sm font-bold">
                            日付を選択
                        </SheetTitle>

                        {/* 授業がある日付の一覧（新しい順）。多いのでスクロールさせる */}
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
