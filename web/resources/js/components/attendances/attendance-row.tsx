import { router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronRight,
    Clock,
    MessageSquareText,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import MakeupLessonSheet from '@/components/attendances/makeup-lesson-sheet';
import StatusBadge from '@/components/attendances/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateLabel, formatMonthDay } from '@/lib/date-format';
import { cn } from '@/lib/utils';
import { makeup, update } from '@/routes/admin/attendances';
import type {
    AttendanceRowData,
    MakeupCandidate,
    RegularRowData,
} from '@/types/attendance';

// 予定や補講に関するタグ（振替予定、来る側の「振替（10/24欠席分）」、「補講あり」など）。
// 後で見る情報なので、塗らずに白地に枠線だけにする
const outlineTagClass =
    'inline-flex items-center gap-1 rounded-full border border-border bg-card px-2 py-0.5 text-xs font-semibold text-muted-foreground';

// 状態を示す小さな色の点（欠席は赤、済みは緑）
function Dot({ className }: { className: string }) {
    return <span className={cn('size-1.5 rounded-full', className)} />;
}

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

    return <span className={outlineTagClass}>補講あり</span>;
}

// 「遅刻」の印。保護者から遅刻の連絡があり、出欠がまだ記録されていない行の、名前のすぐ横に付ける。
// 出席を記録すると、表示ステータスが「出席」になり消える
function LateMark({ row }: { row: AttendanceRowData }) {
    if (row.kind !== 'regular' || row.displayStatus !== '遅刻') {
        return null;
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full border border-status-late/50 bg-status-late-bg px-2 py-0.5 text-xs font-semibold text-status-late-text">
            <Clock className="size-3" />
            遅刻
        </span>
    );
}

// 振替・30分前補講の補講予定を文にしたもの（「振替予定 10/15(木)」など）。それ以外の補講予定ならnull
function makeupPlanText(row: RegularRowData): string | null {
    if (row.makeupType === null || row.makeupDate === null) {
        return null;
    }

    const date = formatDateLabel(row.makeupDate);

    if (row.makeupStatus === '欠席') {
        return `${row.makeupType} ${date} 欠席`;
    }

    if (row.makeupStatus === '出席') {
        return `${row.makeupType}済み ${date}`;
    }

    return `${row.makeupType}予定 ${date}`;
}

// 休んだ日の行に出す、補講予定のタグ。補講の結果によって次のように変わる
// - 未定：「補講未定」（スタッフがあとで補講先を決める必要があるので、未記録と同じオレンジで目立たせる）
// - 振替・30分前補講で、補講先の出欠が未記録：「振替予定 10/15(木)」
// - 補講先で出席：「振替済み 10/15(木)」
// - 補講先で欠席：「振替 10/15(木) 欠席」
// - 補講なし：「補講なし」（補講しないことが決まっていると分かるようにする）
// - 出席の日など：出さない
function MakeupPlanTag({ row }: { row: RegularRowData }) {
    if (row.makeupType === '未定') {
        return (
            <span className="rounded-full bg-brand-accent-tint px-2 py-0.5 text-xs font-semibold text-brand-accent-text">
                補講未定
            </span>
        );
    }

    if (row.makeupType === '補講なし') {
        return <span className={outlineTagClass}>補講なし</span>;
    }

    const text = makeupPlanText(row);

    if (text === null) {
        return null;
    }

    // 補講先で欠席：記録済みなので塗らず、欠席の赤い点だけ付ける
    if (row.makeupStatus === '欠席') {
        return (
            <span className={outlineTagClass}>
                <Dot className="bg-status-absent" />
                {text}
            </span>
        );
    }

    // 済み：補講に出席して対応が終わった状態なので、出席ステータスのバッジと同じ灰色＋緑の点にする
    if (row.makeupStatus === '出席') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-status-pending-bg px-2 py-0.5 text-xs font-semibold text-status-pending-text">
                <Dot className="bg-status-attend" />
                {text}
            </span>
        );
    }

    // 予定：後で見る情報なので、白地に枠線だけにする
    return <span className={outlineTagClass}>{text}</span>;
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
                {/* 遅刻の連絡がある未記録の行は、遅刻は名前の横に出すので、右側は「未記録」にする */}
                <StatusBadge
                    status={
                        row.displayStatus === '遅刻'
                            ? '未記録'
                            : row.displayStatus
                    }
                />
                <MakeupPlanTag row={row} />
            </>
        );
    }

    return (
        <>
            <span className={outlineTagClass}>
                {row.makeupType}（{formatMonthDay(row.absentDate)}欠席分）
            </span>
            {/* 補講先での出欠（まだなら未記録） */}
            <StatusBadge status={row.makeupStatus ?? '未記録'} />
        </>
    );
}

export default function AttendanceRow({
    row,
    makeupCandidates,
    date,
    isFuture,
}: {
    row: AttendanceRowData;
    date: string; // 表示している日付（補講先を選ぶモーダルで、どの欠席の補講かを示すのに使う）
    makeupCandidates: MakeupCandidate[]; // このブロックの補講先の候補
    isFuture: boolean; // まだ来ていない日か（出席や補講先の出欠は記録できず、欠席の事前登録だけできる）
}) {
    const [isOpen, setIsOpen] = useState(!isRecorded(row));

    // ［欠席］を押して、補講予定の選択欄を開いているか（通常の行のみ）
    const [isChoosingMakeup, setIsChoosingMakeup] = useState(false);

    // 補講予定の選択欄を出すか。欠席の記録の行は、補講予定を選び直しやすいよう、開いたときから出しておく
    const showsMakeupOptions =
        isChoosingMakeup || (row.kind === 'regular' && row.status === '欠席');

    // 補講先の授業回を選ぶモーダルを、振替・30分前補講のどちらで開いているか（nullなら閉じている）
    const [pickingMakeupType, setPickingMakeupType] = useState<
        '振替' | '30分前補講' | null
    >(null);

    // ［出席］を押して、補講予定が消える確認を出しているか（通常の行のみ）
    const [isConfirmingPresent, setIsConfirmingPresent] = useState(false);

    // ［出席］にすると消えてしまう補講予定（振替・30分前補講の補講先が決まっている欠席の記録のとき）
    const lostMakeupPlan =
        row.kind === 'regular' && row.status === '欠席'
            ? makeupPlanText(row)
            : null;

    // 送信して、保存できたら行を折りたたむ。保存できなかったらエラーの文言を出す
    function send(url: string, data: Record<string, string | number>) {
        router.patch(url, data, {
            preserveScroll: true, // 画面の一番上に戻らないようにする
            onSuccess: () => {
                // 保存できたら行を折りたたみ、補講予定の選択欄とモーダルも閉じる
                setIsOpen(false);
                setIsChoosingMakeup(false);
                setPickingMakeupType(null);
                setIsConfirmingPresent(false);
            },
            // サーバーの入力チェックで保存できなかったとき（補講先に選べない授業回だった、など）：
            // エラーの文言を画面の下に出す。複数あっても、最初の1つだけ出す
            onError: (errors) => {
                const message = Object.values(errors)[0];

                if (message !== undefined) {
                    toast.error(message, { id: 'attendance-error' });
                }

                setIsConfirmingPresent(false);
            },
        });
    }

    // まだ来ていない日に、記録できないボタン（出席、来る側の行の出欠）を押したとき：理由を画面の下に短く表示する。
    // ボタンをdisabledにするとタップしても何も起きず理由が伝わらないため、見た目だけ薄くして押せるようにしている
    function notifyNotYet() {
        // 同じidのトーストは、すでに出ていれば新しく増やさずに置き換えられるため、何回押しても1つだけになる
        toast('授業の日になると記録できます', { id: 'not-yet' });
    }

    // ［出席］：通常の行は授業の出席、来る側の行は補講先での出席を記録する。
    // 通常の行で、出席にすると補講予定が消える場合は、先に確認を出す
    function handlePresent() {
        if (lostMakeupPlan !== null && !isConfirmingPresent) {
            setIsConfirmingPresent(true);

            return;
        }

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

    // 今の記録（通常の行は授業の出欠、来る側の行は補講先での出欠）。選ばれている方のボタンに色を付けるのに使う
    const currentStatus =
        row.kind === 'regular' ? row.status : row.makeupStatus;

    // 選ばれているボタンの見た目：色で塗らず、濃い枠と✓の形で示す（枠の太さを変えないよう、ringで内側を足す）
    const selectedClass = 'border-foreground ring-1 ring-foreground';

    // ［出席］が選ばれているか：今の記録が出席のとき（欠席を選び直している間は外す）
    const isPresentSelected = currentStatus === '出席' && !isChoosingMakeup;
    // ［欠席］が選ばれているか：今の記録が欠席のとき、または欠席を選んで補講予定を選んでいる間
    const isAbsentSelected = currentStatus === '欠席' || isChoosingMakeup;

    // 補講予定の選択欄で、今の補講予定か（欠席の記録で補講予定があるとき）
    function isMakeupSelected(makeupType: string): boolean {
        return (
            row.kind === 'regular' &&
            row.status === '欠席' &&
            row.makeupType === makeupType
        );
    }

    // 行を閉じる。記録しなくても閉じられるようにする（補講予定の選択欄も閉じる）
    function close() {
        setIsOpen(false);
        setIsChoosingMakeup(false);
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
                        <LateMark row={row} />
                        {/* 保護者からの連絡事項がある印。開くと内容が見える */}
                        {row.note !== null && (
                            <MessageSquareText
                                className="size-3.5 text-muted-foreground"
                                aria-label="連絡事項あり"
                            />
                        )}
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
            {/* 名前の行をタップすると閉じる */}
            <button
                type="button"
                onClick={close}
                className="flex w-full items-start justify-between text-left"
            >
                <span className="block">
                    <span className="flex items-center gap-1.5">
                        <span className="text-sm font-bold">{row.name}</span>
                        <LateMark row={row} />
                        <MakeupMark row={row} />
                    </span>
                    <span className="block text-xs text-text-faint">
                        {row.nameKana}
                    </span>
                </span>
                <span className="flex flex-col items-end gap-1">
                    <span className="flex items-center gap-1.5">
                        <RowBadges row={row} />
                        <ChevronDown className="size-4 text-text-faint" />
                    </span>
                    {/* 誰が記録したか。開いたときだけ出す。スタッフがまだ対応していない保護者の事前連絡は「保護者の連絡」 */}
                    {row.kind === 'regular' &&
                        (row.recordedBy !== null || row.isNotice) && (
                            <span className="text-[10px] text-text-faint">
                                {row.recordedBy !== null
                                    ? `記録：${row.recordedBy}`
                                    : '保護者の連絡'}
                            </span>
                        )}
                </span>
            </button>

            {/* 保護者からの連絡事項 */}
            {row.note !== null && (
                <p className="mt-2 flex items-start gap-1.5 rounded-lg bg-background px-3 py-2 text-xs whitespace-pre-wrap">
                    <MessageSquareText className="mt-px size-3.5 shrink-0 text-muted-foreground" />
                    {row.note}
                </p>
            )}

            <div className="mt-2 flex gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'flex-1',
                        isPresentSelected && selectedClass,
                        // まだ来ていない日は、出席は記録できない（薄く表示して、押したら理由を出す）
                        isFuture && 'opacity-50',
                    )}
                    aria-disabled={isFuture}
                    onClick={isFuture ? notifyNotYet : handlePresent}
                >
                    {isPresentSelected && <Check />}
                    出席
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'flex-1',
                        isAbsentSelected && selectedClass,
                        isFuture && row.kind === 'makeup' && 'opacity-50',
                    )}
                    // 来る側の行（補講先での出欠）は、まだ来ていない日には記録できない（薄く表示して、押したら理由を出す）
                    aria-disabled={isFuture && row.kind === 'makeup'}
                    onClick={
                        isFuture && row.kind === 'makeup'
                            ? notifyNotYet
                            : handleAbsent
                    }
                >
                    {isAbsentSelected && <Check />}
                    欠席
                </Button>
            </div>
            {/* 補講予定の選択欄：［欠席］を押したとき、または欠席の記録の行を開いたときに出す */}
            {showsMakeupOptions && (
                <div className="mt-2.5 border-t border-dashed border-border pt-2">
                    <p className="mb-1.5 text-xs text-muted-foreground">
                        補講予定を選択
                    </p>
                    <div className="grid grid-cols-2 gap-1.5">
                        {/* 振替・30分前補講は、補講先の授業回を選ぶモーダルを開く */}
                        <Button
                            type="button"
                            variant="outline"
                            className={cn(
                                isMakeupSelected('振替') && selectedClass,
                            )}
                            onClick={() => setPickingMakeupType('振替')}
                        >
                            {isMakeupSelected('振替') && <Check />}
                            振替
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className={cn(
                                isMakeupSelected('30分前補講') && selectedClass,
                            )}
                            onClick={() => setPickingMakeupType('30分前補講')}
                        >
                            {isMakeupSelected('30分前補講') && <Check />}
                            30分前補講
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className={cn(
                                isMakeupSelected('未定') && selectedClass,
                            )}
                            onClick={() => recordAbsent('未定')}
                        >
                            {isMakeupSelected('未定') && <Check />}
                            未定
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className={cn(
                                isMakeupSelected('補講なし') && selectedClass,
                            )}
                            onClick={() => recordAbsent('補講なし')}
                        >
                            {isMakeupSelected('補講なし') && <Check />}
                            補講なし
                        </Button>
                    </div>
                </div>
            )}

            {/* 出席にすると補講予定が消えることの確認（補講先が決まっている欠席の記録で［出席］を押したときに開く） */}
            <Dialog
                open={isConfirmingPresent}
                onOpenChange={setIsConfirmingPresent}
            >
                <DialogContent className="gap-3 bg-card p-4">
                    <DialogTitle className="text-base">
                        補講予定が消えます
                    </DialogTitle>
                    {/* モーダルの裏の行が見えなくなるので、誰のいつの記録かを出す（補講先を選ぶモーダルと同じ形） */}
                    {row.kind === 'regular' && (
                        <p className="rounded-lg bg-background px-3 py-2 text-xs text-muted-foreground">
                            <span className="font-bold text-foreground">
                                {row.name}
                            </span>{' '}
                            {formatDateLabel(date)} 第{row.lessonNo}回 の記録
                        </p>
                    )}
                    <DialogDescription>
                        {row.kind === 'regular' && row.isNotice
                            ? '保護者が登録した'
                            : ''}
                        「{lostMakeupPlan}」が消えます。出席にしますか？
                    </DialogDescription>
                    <DialogFooter className="flex-row gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={() => setIsConfirmingPresent(false)}
                        >
                            キャンセル
                        </Button>
                        <Button
                            type="button"
                            className="flex-1"
                            onClick={handlePresent}
                        >
                            出席にする
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* 補講先の授業回を選ぶモーダル（振替・30分前補講を押したときに開く）。通常の行だけで使う */}
            {row.kind === 'regular' && (
                <MakeupLessonSheet
                    makeupType={pickingMakeupType}
                    absence={{ name: row.name, date, lessonNo: row.lessonNo }}
                    candidates={makeupCandidates}
                    onSelect={recordAbsentWithMakeupLesson}
                    onClose={() => setPickingMakeupType(null)}
                />
            )}
        </li>
    );
}
