import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTitle,
} from '@/components/ui/sheet';
import { addDays, formatDateLabel, startOfWeek } from '@/lib/date-format';
import type { MakeupCandidate } from '@/types/attendance';

type Week = {
    weekStart: string; // その週の月曜日
    candidates: MakeupCandidate[];
};

// 候補を週（月曜〜日曜）ごとに分ける。候補は日付順に並んでいるので、週もそのまま日付順になる
function groupByWeek(candidates: MakeupCandidate[]): Week[] {
    const weeks = new Map<string, MakeupCandidate[]>();

    for (const candidate of candidates) {
        const weekStart = startOfWeek(candidate.date);

        weeks.set(weekStart, [...(weeks.get(weekStart) ?? []), candidate]);
    }

    return [...weeks].map(([weekStart, weekCandidates]) => ({
        weekStart,
        candidates: weekCandidates,
    }));
}

/**
 * 振替・30分前補講の補講先の授業回を選ぶモーダル。
 * 開くかどうかは親（行）が決める：makeupTypeがnullなら閉じている。
 * 授業回を選ぶと、そのIDをonSelectで親に伝える（記録するのは親）。
 */
export default function MakeupLessonSheet({
    makeupType,
    candidates,
    onSelect,
    onClose,
}: {
    makeupType: '振替' | '30分前補講' | null;
    candidates: MakeupCandidate[];
    onSelect: (lessonId: number) => void;
    onClose: () => void;
}) {
    return (
        <Sheet
            open={makeupType !== null}
            // ✕・キャンセル・背景のタップで閉じたときに、親に伝える
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <SheetContent
                side="bottom"
                aria-describedby={undefined}
                className="rounded-t-2xl p-4"
            >
                <SheetTitle className="text-sm font-bold">
                    {makeupType === '振替' ? '振替先' : '30分前補講'}
                    の授業回を選択
                </SheetTitle>

                {candidates.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        候補の授業回がありません
                    </p>
                ) : (
                    // 候補は多いので（応用クラスは週4回×5週ほど）、週ごとに見出しを付けてスクロールさせる
                    <div className="max-h-[60dvh] space-y-3 overflow-y-auto">
                        {groupByWeek(candidates).map((week) => (
                            <section key={week.weekStart}>
                                <h3 className="mb-1.5 text-xs font-bold text-muted-foreground">
                                    {formatDateLabel(week.weekStart)}〜
                                    {formatDateLabel(
                                        addDays(week.weekStart, 6),
                                    )}
                                    の週
                                </h3>
                                <ul className="space-y-1.5">
                                    {week.candidates.map((candidate) => (
                                        <li key={candidate.lessonId}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onSelect(candidate.lessonId)
                                                }
                                                className="flex w-full items-center justify-between gap-2 rounded-lg border border-border px-3 py-2.5 text-left text-sm"
                                            >
                                                <span>
                                                    {formatDateLabel(
                                                        candidate.date,
                                                    )}{' '}
                                                    {candidate.startTime}-
                                                    {candidate.endTime}{' '}
                                                    {candidate.title}
                                                </span>
                                                {/* 振替のときだけ、基礎クラスで休んだ回と同じ授業内容の回に印を付ける。
                                                    30分前補講は授業の前の30分で補うもので、厳密には同じ内容ではないため付けない */}
                                                {makeupType === '振替' &&
                                                    candidate.sameContent && (
                                                        <span className="shrink-0 rounded-full bg-primary-tint px-2 py-0.5 text-xs font-semibold text-primary">
                                                            同じ内容
                                                        </span>
                                                    )}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}

                <SheetClose className="h-11 rounded-lg border border-border text-sm font-bold">
                    キャンセル
                </SheetClose>
            </SheetContent>
        </Sheet>
    );
}
