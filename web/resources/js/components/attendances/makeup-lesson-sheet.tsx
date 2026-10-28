import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    addDays,
    formatDateLabel,
    startOfWeek,
    subtractMinutes,
} from '@/lib/date-format';
import { cn } from '@/lib/utils';
import type { MakeupCandidate } from '@/types/attendance';

type Week = {
    weekStart: string; // その週の月曜日
    candidates: MakeupCandidate[];
    // その週の授業計画上の第何回か。休校は週単位で決まるため、ふつうは1つ（ずれていた場合に備えて、複数あれば小さい順）
    lessonNos: number[];
    hasSameContent: boolean; // 休んだ回と同じ回の授業回がある週か
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
        lessonNos: [...new Set(weekCandidates.map((c) => c.lessonNo))].sort(
            (a, b) => a - b,
        ),
        hasSameContent: weekCandidates.some((c) => c.sameContent),
    }));
}

/**
 * 振替・30分前補講で代わりに来る日（補講先の授業回）を選ぶモーダル。
 * 開くかどうかは親（行）が決める：makeupTypeがnullなら閉じている。
 * 授業回を選ぶと、そのIDをonSelectで親に伝える（記録するのは親）。
 */
export default function MakeupLessonSheet({
    makeupType,
    absence,
    candidates,
    onSelect,
    onClose,
}: {
    makeupType: '振替' | '30分前補講' | null;
    absence: { name: string; date: string; lessonNo: number }; // どの欠席の補講を選んでいるか
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
                className="rounded-t-2xl bg-card p-4"
            >
                <SheetTitle className="text-sm font-bold">
                    {makeupType === '振替' ? '振替日' : '30分前補講の日'}
                    を選択
                </SheetTitle>

                {/* どの欠席の補講を選んでいるか。モーダルで行が隠れても分かるようにする */}
                <p className="rounded-lg bg-background px-3 py-2 text-xs text-muted-foreground">
                    <span className="font-bold text-foreground">
                        {absence.name}
                    </span>{' '}
                    {formatDateLabel(absence.date)} 第{absence.lessonNo}回
                    の欠席分
                </p>

                {candidates.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        選べる日がありません
                    </p>
                ) : (
                    // 候補は多いので（応用クラスは週4回×5週ほど）、週ごとに見出しを付けてスクロールさせる
                    <div className="max-h-[60dvh] space-y-3 overflow-y-auto">
                        {groupByWeek(candidates).map((week) => (
                            // 振替のときだけ、休んだ回と同じ回の週を、左の青い線と「同じ回」のタグで示す。
                            // 30分前補講は授業の前の30分で補うもので、厳密には同じ内容ではないため示さない。
                            // 面を塗るとそこだけ押せるように見えるため、線にしている（ヘッダーの第何回と同じ印）。
                            // どの週も左に線の幅の余白を取り、線がある週とない週で候補の位置がずれないようにする
                            <section
                                key={week.weekStart}
                                className={cn(
                                    'border-l-3 border-transparent pl-2',
                                    makeupType === '振替' &&
                                        week.hasSameContent &&
                                        'border-primary',
                                )}
                            >
                                {/* 見出し：その週の授業計画上の第何回か（応用クラスは参考）と、週の範囲 */}
                                <h3 className="mb-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span className="text-sm font-bold text-foreground">
                                        第{week.lessonNos.join('・')}回
                                    </span>
                                    <span>
                                        {formatDateLabel(week.weekStart)}〜
                                        {formatDateLabel(
                                            addDays(week.weekStart, 6),
                                        )}
                                    </span>
                                    {makeupType === '振替' &&
                                        week.hasSameContent && (
                                            <span className="ml-auto rounded-full bg-primary-tint px-2 py-0.5 font-bold text-primary">
                                                同じ回
                                            </span>
                                        )}
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
                                                {/* 1行目：日付・時間・クラス。日付・時間・クラスのそれぞれの途中では改行しない。
                                                    30分前補講は、2行目に実際に来てもらう時間（授業の30分前）を太字で出す */}
                                                <span className="flex flex-col">
                                                    <span className="flex flex-wrap gap-x-1">
                                                        <span className="whitespace-nowrap">
                                                            {formatDateLabel(
                                                                candidate.date,
                                                            )}
                                                        </span>
                                                        <span className="whitespace-nowrap">
                                                            {`${candidate.startTime}-${candidate.endTime}`}
                                                        </span>
                                                        <span className="whitespace-nowrap">
                                                            {candidate.title}
                                                        </span>
                                                    </span>
                                                    {makeupType ===
                                                        '30分前補講' && (
                                                        <span className="mt-0.5 text-xs">
                                                            来る時間{' '}
                                                            <b className="text-sm">
                                                                {subtractMinutes(
                                                                    candidate.startTime,
                                                                    30,
                                                                )}
                                                            </b>
                                                        </span>
                                                    )}
                                                </span>
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
