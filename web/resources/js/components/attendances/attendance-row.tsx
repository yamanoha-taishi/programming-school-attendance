import { router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import MakeupLessonSheet from '@/components/attendances/makeup-lesson-sheet';
import StatusBadge from '@/components/attendances/status-badge';
import { Button } from '@/components/ui/button';
import { formatDateLabel, formatMonthDay } from '@/lib/date-format';
import { cn } from '@/lib/utils';
import { makeup, update } from '@/routes/admin/attendances';
import type {
    AttendanceRowData,
    MakeupCandidate,
    RegularRowData,
} from '@/types/attendance';

// 補講の種類ごとの色（淡い背景＋同系統の文字色）
const makeupTypeColors: Record<string, string> = {
    振替: 'bg-status-transfer-bg text-status-transfer-text',
    '30分前補講': 'bg-status-makeup-bg text-status-makeup',
};

// 記録済みか（出欠サマリーで「未記録」に数えない行か）
function isRecorded(row: AttendanceRowData): boolean {
    return row.kind === 'regular'
        ? row.status !== null
        : row.makeupStatus !== null;
}

// 「補講あり」の印。同じブロックの末尾に、この生徒の補講の行もある通常の行に付ける
function MakeupMark({ row }: { row: AttendanceRowData }) {
    if (row.kind !== 'regular' || !row.hasMakeup) {
        return null;
    }

    return (
        <span className="rounded-full bg-status-makeup-bg px-2 py-0.5 text-xs font-semibold text-status-makeup">
            補講あり
        </span>
    );
}

// 休んだ日の行に出す、補講予定のタグ。補講の結果によって次のように変わる
// - 未定：「補講未定」（スタッフがあとで補講先を決める必要があることに気づけるようにする）
// - 振替・30分前補講で、補講先の出欠が未記録：「振替予定 10/15(木)」
// - 補講先で出席：「振替済み 10/15(木)」
// - 補講先で欠席：「振替 10/15(木) 欠席」
// - 補講なし：「補講なし」（補講しないことが決まっていると分かるようにする）
// - 出席の日など：出さない
function MakeupPlanTag({ row }: { row: RegularRowData }) {
    if (row.makeupType === '未定') {
        return (
            <span className="rounded-full bg-status-pending-bg px-2 py-0.5 text-xs font-semibold text-status-pending-text">
                補講未定
            </span>
        );
    }

    if (row.makeupType === '補講なし') {
        // 決まっていて対応が要らないものなので、未定（灰色の背景）と区別して枠線だけにする
        return (
            <span className="rounded-full border border-border px-2 py-0.5 text-xs font-semibold text-muted-foreground">
                補講なし
            </span>
        );
    }

    if (row.makeupType === null || row.makeupDate === null) {
        return null;
    }

    const date = formatDateLabel(row.makeupDate);

    if (row.makeupStatus === '欠席') {
        return (
            <span className="rounded-full bg-status-absent-bg px-2 py-0.5 text-xs font-semibold text-status-absent-text">
                {row.makeupType} {date} 欠席
            </span>
        );
    }

    // 済み：出席ステータスの「振替済み」「30分前補講済み」のバッジの代わりなので、同じ色（種類ごとの色）にする
    if (row.makeupStatus === '出席') {
        return (
            <span
                className={`rounded-full px-2 py-0.5 text-xs font-semibold ${makeupTypeColors[row.makeupType] ?? ''}`}
            >
                {row.makeupType}済み {date}
            </span>
        );
    }

    // 予定：まだ先の予定であることがパッと見で分かるよう、種類によらず情報の色（青）にする
    return (
        <span className="rounded-full bg-primary-tint px-2 py-0.5 text-xs font-semibold text-primary">
            {row.makeupType}予定 {date}
        </span>
    );
}

// 行の右側に出すバッジ
function RowBadges({ row }: { row: AttendanceRowData }) {
    if (row.kind === 'regular') {
        // 補講先で出席済みなら、補講予定のタグが「振替済み 10/15(木)」になり、
        // 表示ステータス（振替済み）と同じ内容になるので、タグだけを出す
        if (row.makeupStatus === '出席') {
            return <MakeupPlanTag row={row} />;
        }

        return (
            <>
                <StatusBadge status={row.displayStatus} />
                <MakeupPlanTag row={row} />
            </>
        );
    }

    return (
        <>
            <span
                className={`rounded-full px-2 py-0.5 text-xs font-semibold ${makeupTypeColors[row.makeupType] ?? ''}`}
            >
                {row.makeupType}（{formatMonthDay(row.absentDate)}欠席分）
            </span>
            {row.makeupStatus !== null && (
                <StatusBadge status={row.makeupStatus} />
            )}
        </>
    );
}

export default function AttendanceRow({
    row,
    makeupCandidates,
}: {
    row: AttendanceRowData;
    makeupCandidates: MakeupCandidate[]; // このブロックの補講先の候補
}) {
    const [isOpen, setIsOpen] = useState(!isRecorded(row));

    // ［欠席］を押して、補講予定の選択欄を開いているか（通常の行のみ）
    const [isChoosingMakeup, setIsChoosingMakeup] = useState(false);

    // 補講先の授業回を選ぶモーダルを、振替・30分前補講のどちらで開いているか（nullなら閉じている）
    const [pickingMakeupType, setPickingMakeupType] = useState<
        '振替' | '30分前補講' | null
    >(null);

    // 送信して、保存できたら行を折りたたむ
    function send(url: string, data: Record<string, string | number>) {
        router.patch(url, data, {
            preserveScroll: true, // 画面の一番上に戻らないようにする
            onSuccess: () => {
                // 保存できたら行を折りたたみ、補講予定の選択欄とモーダルも閉じる
                setIsOpen(false);
                setIsChoosingMakeup(false);
                setPickingMakeupType(null);
            },
        });
    }

    // ［出席］：通常の行は授業の出席、来る側の行は補講先での出席を記録する
    function handlePresent() {
        if (row.kind === 'regular') {
            send(update({ lesson: row.lessonId, student: row.studentId }).url, {
                status: '出席',
            });
        } else {
            send(makeup(row.attendanceId).url, { makeup_status: '出席' });
        }
    }

    // ［欠席］：来る側の行は補講先での欠席を記録する。通常の行は、補講予定の選択欄を開く（まだ送らない）
    function handleAbsent() {
        if (row.kind === 'makeup') {
            send(makeup(row.attendanceId).url, { makeup_status: '欠席' });

            return;
        }

        setIsChoosingMakeup(true);
    }

    // ［未定］［補講なし］：補講先の授業回を選ばないので、その場で欠席を記録する
    function recordAbsent(makeupType: '未定' | '補講なし') {
        if (row.kind !== 'regular') {
            return;
        }

        send(update({ lesson: row.lessonId, student: row.studentId }).url, {
            status: '欠席',
            makeup_type: makeupType,
        });
    }

    // ［振替］［30分前補講］：モーダルで補講先の授業回を選んだ時点で、欠席を記録する
    function recordAbsentWithMakeupLesson(makeupLessonId: number) {
        if (row.kind !== 'regular' || pickingMakeupType === null) {
            return;
        }

        send(update({ lesson: row.lessonId, student: row.studentId }).url, {
            status: '欠席',
            makeup_type: pickingMakeupType,
            makeup_lesson_id: makeupLessonId,
        });
    }

    // 折りたたみ：名前とバッジだけの1行。タップすると開く
    if (!isOpen) {
        return (
            <li className="border-b border-border bg-card">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="flex w-full items-center justify-between px-3 py-2 text-left"
                >
                    <span className="flex items-center gap-1.5">
                        <span className="text-sm">{row.name}</span>
                        <MakeupMark row={row} />
                    </span>
                    <span className="flex items-center gap-1.5">
                        <RowBadges row={row} />
                        <ChevronRight className="size-4 text-text-faint" />
                    </span>
                </button>
            </li>
        );
    }

    // 展開：名前・カナ・バッジと、出席／欠席のボタン
    return (
        <li className="border-b border-border bg-card px-3 py-2.5">
            <div className="flex items-center justify-between">
                <div>
                    <div className="flex items-center gap-1.5">
                        <span className="text-sm font-bold">{row.name}</span>
                        <MakeupMark row={row} />
                    </div>
                    <div className="text-xs text-text-faint">
                        {row.nameKana}
                    </div>
                </div>
                <div className="flex items-center gap-1.5">
                    <RowBadges row={row} />
                </div>
            </div>

            <div className="mt-2 flex gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    className="flex-1"
                    onClick={handlePresent}
                >
                    出席
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'flex-1',
                        isChoosingMakeup &&
                            'border-status-absent bg-status-absent-bg text-status-absent-text',
                    )}
                    onClick={handleAbsent}
                >
                    欠席
                </Button>
            </div>
            {/* 補講予定の選択欄：［欠席］を押したときだけ開く */}
            {isChoosingMakeup && (
                <div className="mt-2.5 border-t border-dashed border-border pt-2">
                    <p className="mb-1.5 text-xs text-muted-foreground">
                        補講予定を選択
                    </p>
                    <div className="grid grid-cols-2 gap-1.5">
                        {/* 振替・30分前補講は、補講先の授業回を選ぶモーダルを開く */}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPickingMakeupType('振替')}
                        >
                            振替
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPickingMakeupType('30分前補講')}
                        >
                            30分前補講
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => recordAbsent('未定')}
                        >
                            未定
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => recordAbsent('補講なし')}
                        >
                            補講なし
                        </Button>
                    </div>
                </div>
            )}

            {/* 補講先の授業回を選ぶモーダル（振替・30分前補講を押したときに開く） */}
            <MakeupLessonSheet
                makeupType={pickingMakeupType}
                candidates={makeupCandidates}
                onSelect={recordAbsentWithMakeupLesson}
                onClose={() => setPickingMakeupType(null)}
            />
        </li>
    );
}
