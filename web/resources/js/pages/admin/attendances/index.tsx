import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import AttendanceBlock from '@/components/attendances/attendance-block';
import DateNav from '@/components/attendances/date-nav';
import DaySummary from '@/components/attendances/day-summary';
import type {
    AttendanceBlockData,
    AttendanceSummary,
    CalendarData,
    UpcomingHoliday,
} from '@/types/attendance';

type Props = {
    date: string;
    today: string;
    prevDate: string | null; // ◀で移る、前の選べる授業日
    nextDate: string | null; // ▶で移る、次の選べる授業日
    firstSelectableDate: string | null; // 選べる授業日の最初（カレンダーで前の月へ移れる範囲）
    lastSelectableDate: string | null; // 選べる授業日の最後（カレンダーで次の月へ移れる範囲）
    calendar: CalendarData; // 表示中の日付の年度のカレンダー
    advanceUntil: string; // 事前に登録できる期限の目安（今日から5週間後）
    advanceWeeks: number; // 事前に登録できる期限が、今日から何週間後か
    upcomingHolidays: UpcomingHoliday[]; // 今後の休校（近い順に最大3件）
    isFuture: boolean; // まだ来ていない日か（未来の日付では、欠席の事前登録だけできる）
    daySummary: AttendanceSummary;
    blocks: AttendanceBlockData[];
    lessonNos: number[]; // 授業計画上の第何回か
};

export default function AttendancesIndex({
    date,
    today,
    prevDate,
    nextDate,
    firstSelectableDate,
    lastSelectableDate,
    calendar,
    advanceUntil,
    advanceWeeks,
    upcomingHolidays,
    isFuture,
    daySummary,
    blocks,
    lessonNos,
}: Props) {
    return (
        <>
            <Head title="出席記録・一覧" />
            {/* 日付と出欠サマリーは、スクロールしても画面の上に固定表示する */}
            <header className="sticky top-0 z-10 border-b border-border bg-card px-3 py-2.5">
                <DateNav
                    date={date}
                    today={today}
                    prevDate={prevDate}
                    nextDate={nextDate}
                    firstSelectableDate={firstSelectableDate}
                    lastSelectableDate={lastSelectableDate}
                    calendar={calendar}
                    advanceUntil={advanceUntil}
                    advanceWeeks={advanceWeeks}
                    lessonNos={lessonNos}
                    upcomingHolidays={upcomingHolidays}
                />
                <DaySummary summary={daySummary} />
            </header>
            {/* まだ来ていない日の説明。行ごとには出さず、ページに1回だけ出す */}
            {isFuture && (
                <p className="flex items-start gap-1.5 border-b border-border bg-muted px-3 py-2 text-xs text-foreground">
                    <Info className="mt-px size-3.5 shrink-0" />
                    先の日付です。欠席の連絡だけ事前に登録できます。
                </p>
            )}
            {blocks.length === 0 && (
                <p className="px-3 py-10 text-center text-sm text-muted-foreground">
                    この日は授業がありません
                </p>
            )}
            {blocks.map((block) => (
                <AttendanceBlock
                    key={block.sectionId}
                    block={block}
                    date={date}
                    isFuture={isFuture}
                />
            ))}
        </>
    );
}
