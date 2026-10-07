import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
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
        <div className="flex items-center justify-between">
            <div className="flex items-center gap-2.5">
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

                <span className="w-30 rounded-full border border-primary bg-primary-tint px-2 py-1 text-center text-sm font-bold text-primary">
                    {formatDateLabel(date)}
                </span>

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
                className="rounded-full border border-primary bg-card px-2.5 py-1 text-xs text-primary"
            >
                今日
            </Link>
        </div>
    );
}
