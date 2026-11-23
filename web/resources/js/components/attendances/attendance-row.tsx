import { router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    CircleDashed,
    ChevronRight,
    Clock,
    History,
    X,
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
import { absentDatesLabel, arrivalTime, makeupLabel } from '@/lib/makeup';
import { cn } from '@/lib/utils';
import { makeup, update } from '@/routes/admin/attendances';
import type {
    AttendanceRowData,
    MakeupCandidate,
    RegularRowData,
} from '@/types/attendance';

// 予定や補講に関するタグ（補講の種類、補講なしなど）。
// 後で見る情報なので、塗らずに白地に枠線だけにする。日付などの変わる値はタグに入れず、名前の下に出す
const outlineTagClass =
    'inline-flex items-center gap-1 rounded-full border border-border bg-card px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-muted-foreground';

// 記録してほしい補講のタグ（補講未定、補講の出欠が未記録の補講）。未記録と同じオレンジで目立たせる
const pendingTagClass =
    'rounded-full bg-brand-accent-tint px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-brand-accent-text';

// 記録済みか（出欠サマリーで「未記録」に数えない行か）。
// 補講でも来る通常の行は、補講の出欠まで記録して記録済みにする
function isRecorded(row: AttendanceRowData): boolean {
    if (row.kind === 'makeup') {
        return row.makeupStatus !== null;
    }

    return (
        row.status !== null &&
        (row.catchUp === null || row.catchUp.makeupStatus !== null)
    );
}

// 補講の状態のアイコン：補講先の出欠が未記録なら時計（予定）、出席なら✓（済み）、欠席なら赤い×
function MakeupStatusIcon({ status }: { status: string | null }) {
    if (status === '出席') {
        return <Check className="size-3" />;
    }

    if (status === '欠席') {
        return <X className="size-3 text-status-absent" />;
    }

    return <Clock className="size-3" />;
}

// 自分のクラスのこの回に補講でも来る生徒の、補講のタグ
// - 補講の出欠が未記録：「13:30〜 1時間前補講」（授業の前に補講があることを示し、記録してほしいのでオレンジにする）
// - 記録済み：✓（出席）・赤い×（欠席）のアイコン＋補講の種類
function CatchUpTag({
    row,
    startTime,
}: {
    row: AttendanceRowData;
    startTime: string;
}) {
    if (row.kind !== 'regular' || row.catchUp === null) {
        return null;
    }

    const { makeupTypes, makeupStatus } = row.catchUp;
    const label = makeupLabel(makeupTypes);

    if (makeupStatus === null) {
        const arrival = arrivalTime(startTime, makeupTypes);

        return (
            <span className={pendingTagClass}>
                {arrival !== null && `${arrival}〜 `}
                {label}
            </span>
        );
    }

    return (
        <span className={outlineTagClass}>
            <MakeupStatusIcon status={makeupStatus} />
            {label}
        </span>
    );
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

// 休んだ日の行の補講の種類。同じ回に30分前補講が2回分入っていれば「1時間前補講」にする
// （振替＋30分前補講は、それぞれの欠席の行に「振替」「30分前補講」と出す）
function absentMakeupLabel(row: RegularRowData): string {
    if (row.makeupType === '30分前補講') {
        return makeupLabel(
            row.makeupGroupTypes.filter((type) => type === '30分前補講'),
        );
    }

    return row.makeupType ?? '';
}

// 振替・30分前補講の補講予定を文にしたもの（確認のダイアログで使う。「振替予定 10/15(木)」など）。
// それ以外の補講予定ならnull
function makeupPlanText(row: RegularRowData): string | null {
    if (row.makeupType === null || row.makeupDate === null) {
        return null;
    }

    const label = absentMakeupLabel(row);
    const date = formatDateLabel(row.makeupDate);

    if (row.makeupStatus === '欠席') {
        return `${label} ${date} 欠席`;
    }

    if (row.makeupStatus === '出席') {
        return `${label}済み ${date}`;
    }

    return `${label}予定 ${date}`;
}

// 休んだ日の行に出す、補講予定のタグ
// - 未定：「補講未定」（スタッフがあとで補講先を決める必要があるので、オレンジで目立たせる）
// - 振替・30分前補講：状態のアイコン（時計＝予定、✓＝済み、赤い×＝補講先で欠席）＋補講の種類。補講日は名前の下に出す
// - 補講なし：「補講なし」（補講しないことが決まっていると分かるようにする）
// - 出席の日など：出さない
function MakeupPlanTag({ row }: { row: RegularRowData }) {
    if (row.makeupType === '未定') {
        return <span className={pendingTagClass}>補講未定</span>;
    }

    if (row.makeupType === '補講なし') {
        return <span className={outlineTagClass}>補講なし</span>;
    }

    if (row.makeupType === null || row.makeupDate === null) {
        return null;
    }

    return (
        <span className={outlineTagClass}>
            <MakeupStatusIcon status={row.makeupStatus} />
            {absentMakeupLabel(row)}
        </span>
    );
}

// 名前の下に出す、補講の日付
// - 補講でも来る行・来る側の行：何日の欠席分か（「11/7・11/14欠席分」）
// - 休んだ日の行で、補講先が決まっているとき：補講日（「補講日 10/28(水)」）
function makeupSubText(row: AttendanceRowData): string | null {
    if (row.kind === 'makeup') {
        return absentDatesLabel(row.absences.map((a) => a.absentDate));
    }

    if (row.catchUp !== null) {
        return absentDatesLabel(row.catchUp.absences.map((a) => a.absentDate));
    }

    if (row.status === '欠席' && row.makeupDate !== null) {
        return `補講日 ${formatDateLabel(row.makeupDate)}`;
    }

    return null;
}

// 通常の行の右端のバッジに出す、この回の出欠
// - 遅刻の連絡がある未記録の行：遅刻は名前の横に出すので「未記録」
// - 振替済み・30分前補講済み：この回は休んでいるので「欠席」（補講の結果は補講予定のタグで示す）
function regularBadgeStatus(row: RegularRowData): string {
    if (row.displayStatus === '遅刻') {
        return '未記録';
    }

    if (row.status === '欠席') {
        return '欠席';
    }

    return row.displayStatus;
}

// 行の右側に出すタグとバッジ。右端のバッジは、いつも「この回の出欠」にする（タグはその左に並べる）
function RowBadges({
    row,
    startTime,
}: {
    row: AttendanceRowData;
    startTime: string;
}) {
    if (row.kind === 'regular') {
        return (
            <>
                <CatchUpTag row={row} startTime={startTime} />
                <MakeupPlanTag row={row} />
                <StatusBadge status={regularBadgeStatus(row)} />
            </>
        );
    }

    return (
        <>
            <span className={outlineTagClass}>
                {makeupLabel(row.makeupTypes)}
            </span>
            {/* 補講先での出欠（まだなら未記録） */}
            <StatusBadge status={row.makeupStatus ?? '未記録'} />
        </>
    );
}

// 欠席した日の保護者からの連絡事項。今日休むと読み違えないよう、見出しで欠席した日を示し、
// 左に線を引いた引用の形・灰色の文字にする
function AbsenceNotes({
    absences,
}: {
    absences: { absentDate: string; note: string | null }[];
}) {
    return absences
        .filter((absence) => absence.note !== null)
        .map((absence) => (
            <div
                key={absence.absentDate}
                className="mt-2 border-l-3 border-border py-0.5 pl-2.5 text-xs text-muted-foreground"
            >
                <p className="mb-0.5 flex items-center gap-1 font-bold">
                    <History className="size-3.5 shrink-0" />
                    {formatDateLabel(absence.absentDate)} 欠席時の連絡
                </p>
                <p className="whitespace-pre-wrap">{absence.note}</p>
            </div>
        ));
}

// 補講の見出し（「1時間前補講 13:30〜（11/7・11/14欠席分）」）
// recordedByを渡すと、右に補講先での出欠を記録したスタッフ（「記録：〇〇」）も出す
function MakeupHeading({
    group,
    startTime,
    prefix = '',
    recordedBy = null,
}: {
    group: { makeupTypes: string[]; absences: { absentDate: string }[] };
    startTime: string;
    prefix?: string;
    recordedBy?: string | null;
}) {
    const arrival = arrivalTime(startTime, group.makeupTypes) ?? startTime;

    return (
        <div className="mb-1.5 flex items-baseline justify-between gap-2">
            <p className="text-xs font-bold">
                {prefix}
                {makeupLabel(group.makeupTypes)} {arrival}〜
                <span className="font-normal text-muted-foreground">
                    （
                    {absentDatesLabel(group.absences.map((a) => a.absentDate))}
                    ）
                </span>
            </p>
            {recordedBy !== null && (
                <span className="shrink-0 text-[10px] text-text-faint">
                    記録：{recordedBy}
                </span>
            )}
        </div>
    );
}

export default function AttendanceRow({
    row,
    makeupCandidates,
    date,
    startTime,
    isFuture,
}: {
    row: AttendanceRowData;
    date: string; // 表示している日付（補講先を選ぶモーダルで、どの欠席の補講かを示すのに使う）
    startTime: string; // この授業回の開始時刻（30分前補講で来る時間を出すのに使う）
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

    // 記録が消える操作の前に出す確認（nullなら閉じている）。
    // 確認で［出席にする］［変更する］を押したら、runで記録する
    const [confirmation, setConfirmation] = useState<{
        title: string;
        message: string;
        confirmLabel: string;
        run: () => void;
    } | null>(null);

    // 今の補講予定を文にしたもの（振替・30分前補講の補講先が決まっている欠席の記録のとき。「振替済み 10/29(木)」など）
    const currentMakeupPlan =
        row.kind === 'regular' && row.status === '欠席'
            ? makeupPlanText(row)
            : null;

    // 同じ補講先の回に入っている、ほかの欠席分の補講の欠席日（1時間前補講・振替＋30分前補講のもう1回分）。
    // 補講予定を変えても、そちらの補講予定と記録は残るので、確認の文で示す
    const otherAbsentDatesInGroup =
        row.kind === 'regular' && row.makeupSessionKey !== null
            ? row.otherMakeups
                  .filter((other) => other.sessionKey === row.makeupSessionKey)
                  .map((other) => other.absentDate)
            : [];

    // 確認の文で、消えるものを示す。
    // 同じ回にほかの欠席分の補講もあるときは、この欠席分だけが消えて、もう1回分は残ることを書く
    function lostText(plan: string, what: string): string {
        if (otherAbsentDatesInGroup.length === 0) {
            return `「${plan}」${what}が消えます。`;
        }

        return `「${plan}」のうち、${formatMonthDay(date)}欠席分${what}が消えます。${otherAbsentDatesInGroup.map(formatMonthDay).join('・')}欠席分は残ります。`;
    }

    // 補講先での出欠まで記録済みか（振替済み・30分前補講済み、または補講先で欠席）。
    // 補講予定を変えると、この記録が消える
    const hasMakeupResult = row.kind === 'regular' && row.makeupStatus !== null;

    // 送信して、保存できたら行を折りたたむ（keepOpenなら開いたままにする）。保存できなかったらエラーの文言を出す
    function send(
        url: string,
        data: Record<string, string | number>,
        keepOpen = false,
    ) {
        router.patch(url, data, {
            preserveScroll: true, // 画面の一番上に戻らないようにする
            // 日付選択のカレンダーのデータ（1年度分）は受け取り直さない。次に日付を切り替えたときに新しくなる
            except: ['calendar'],
            onSuccess: () => {
                // 保存できたら行を折りたたみ、補講予定の選択欄とモーダルも閉じる
                setIsOpen(keepOpen);
                setIsChoosingMakeup(false);
                setPickingMakeupType(null);
                setConfirmation(null);
            },
            // サーバーの入力チェックで保存できなかったとき（補講先に選べない授業回だった、など）：
            // エラーの文言を画面の下に出す。複数あっても、最初の1つだけ出す
            onError: (errors) => {
                const message = Object.values(errors)[0];

                if (message !== undefined) {
                    toast.error(message, { id: 'attendance-error' });
                }

                setConfirmation(null);
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
        if (row.kind === 'makeup') {
            send(makeup(row.attendanceId).url, { makeup_status: '出席' });

            return;
        }

        const recordPresent = () =>
            send(update({ lesson: row.lessonId, student: row.studentId }).url, {
                status: '出席',
            });

        if (currentMakeupPlan === null) {
            recordPresent();

            return;
        }

        setConfirmation({
            title: '補講予定が消えます',
            message: `${row.isNotice ? '保護者が登録した' : ''}${lostText(currentMakeupPlan, '')}出席にしますか？`,
            confirmLabel: '出席にする',
            run: recordPresent,
        });
    }

    // 補講先での出欠まで記録済みの欠席で、補講予定を変えるとき：補講の記録が消えるので、先に確認を出す。
    // それ以外はそのまま記録する
    function confirmMakeupChange(record: () => void) {
        if (!hasMakeupResult || currentMakeupPlan === null) {
            record();

            return;
        }

        setConfirmation({
            title: '補講の記録が消えます',
            message: `${lostText(currentMakeupPlan, 'の記録')}補講予定を変えますか？`,
            confirmLabel: '変更する',
            run: record,
        });
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

        const { lessonId, studentId } = row;

        confirmMakeupChange(() =>
            send(update({ lesson: lessonId, student: studentId }).url, {
                status: '欠席',
                makeup_type: makeupType,
            }),
        );
    }

    // ［振替］［30分前補講］：モーダルで補講先の授業回を選んだ時点で、欠席を記録する
    function recordAbsentWithMakeupLesson(makeupLessonId: number) {
        if (row.kind !== 'regular' || pickingMakeupType === null) {
            return;
        }

        const { lessonId, studentId } = row;
        const makeupType = pickingMakeupType;
        const record = () =>
            send(update({ lesson: lessonId, student: studentId }).url, {
                status: '欠席',
                makeup_type: makeupType,
                makeup_lesson_id: makeupLessonId,
            });

        // 同じ補講予定（種類と補講先）を選び直したときは、補講の記録は残る（サーバー側で残す）ので確認しない。
        // 補講先は、同じ日の同じ回なら同じとみなす（応用クラスは、B1〜B6のどの組の授業回でも同じ回）
        const selected = makeupCandidates.find(
            (candidate) => candidate.lessonId === makeupLessonId,
        );

        if (
            row.makeupType === makeupType &&
            row.makeupSessionKey !== null &&
            row.makeupSessionKey === selected?.sessionKey
        ) {
            record();

            return;
        }

        // 確認を出すときは、補講先を選ぶモーダルを先に閉じる
        if (hasMakeupResult) {
            setPickingMakeupType(null);
        }

        confirmMakeupChange(record);
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

    // 欠席の記録か（補講予定まで記録済み）
    const isAbsentRecorded = row.kind === 'regular' && row.status === '欠席';

    // まだ押す必要がある範囲の見た目：押し忘れを防ぐため、ボタンは普通のまま、その範囲を薄いオレンジで囲む。
    // 記録が済んだら囲みは消える（選んだボタンは濃い枠＋✓）
    const pendingAreaClass =
        'rounded-lg border border-brand-accent/30 bg-brand-accent-tint/50 p-1.5';
    // 囲みの中の見出しは、要対応のオレンジの文字にする
    const pendingLabelClass = 'font-bold text-brand-accent-text';

    // ［出席］［欠席］を押す必要があるか：出欠がまだ記録されていないとき。
    // まだ来ていない日は、記録しなくてよい（欠席の連絡があったときだけ登録する）ので除く
    const needsAttendance =
        !isFuture && currentStatus === null && !isChoosingMakeup;

    // 補講予定の4つのどれかを押す必要があるか：［欠席］を押したが、補講予定をまだ選んでいない（まだ欠席が記録されていない）とき
    const needsMakeupChoice = isChoosingMakeup && !isAbsentRecorded;

    // 補講日を選ぶ必要があるか：補講未定の欠席で、補講先を決める必要があるとき（［欠席］を押して選び直している間は除く）
    const needsMakeupLesson =
        isAbsentRecorded &&
        row.kind === 'regular' &&
        row.makeupType === '未定' &&
        !isChoosingMakeup;

    // 補講予定の選択欄で、今の補講予定か（欠席の記録で補講予定があるとき）
    function isMakeupSelected(makeupType: string): boolean {
        return (
            row.kind === 'regular' &&
            row.status === '欠席' &&
            row.makeupType === makeupType
        );
    }

    // 自分のクラスのこの回に補講でも来る場合の、補講の情報（通常の行のみ）
    const catchUp = row.kind === 'regular' ? row.catchUp : null;

    // 名前の下に出す、補講の日付
    const subText = makeupSubText(row);

    // 補講で来る分の、欠席した日の連絡事項があるか
    // この回の記録を誰がしたか（「記録：山田 太郎」。スタッフがまだ対応していない保護者の事前連絡は「保護者の連絡」）。
    // 来る側の行は、補講先での出欠を記録したスタッフ
    const recorderText =
        row.kind === 'regular' && row.recordedBy === null
            ? row.isNotice
                ? '保護者の連絡'
                : null
            : row.recordedBy !== null
              ? `記録：${row.recordedBy}`
              : null;

    // 補講でも来る行で、補講と授業の記録をした人を、それぞれの段に分けて出すか。
    // ふつうは同じ人が続けて記録するので、同じ人なら右上に1つにまとめ、違う人（片方だけ記録済みを含む）のときだけ分ける
    const showsRecorderPerSection =
        catchUp !== null &&
        !(catchUp.recordedBy !== null && catchUp.recordedBy === row.recordedBy);

    const hasAbsenceNote = (
        row.kind === 'makeup' ? row.absences : (catchUp?.absences ?? [])
    ).some((absence) => absence.note !== null);

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
                    <span className="block">
                        <span className="flex items-center gap-1.5">
                            <span className="text-sm">{row.name}</span>
                            <LateMark row={row} />
                            {/* 保護者からの連絡事項がある印。開くと内容が見える */}
                            {row.kind === 'regular' && row.note !== null && (
                                <MessageSquareText
                                    className="size-3.5 text-muted-foreground"
                                    aria-label="連絡事項あり"
                                />
                            )}
                            {/* 補講で来る分の、欠席した日の連絡（前の記録）がある印。吹き出しではなく時計の矢印にして区別する */}
                            {hasAbsenceNote && (
                                <History
                                    className="size-3.5 text-muted-foreground"
                                    aria-label="欠席時の連絡あり"
                                />
                            )}
                        </span>
                        {subText !== null && (
                            <span className="block text-[11px] text-muted-foreground">
                                {subText}
                            </span>
                        )}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <RowBadges row={row} startTime={startTime} />
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
                    </span>
                    <span className="block text-xs text-text-faint">
                        {row.nameKana}
                    </span>
                    {subText !== null && (
                        <span className="block text-[11px] text-muted-foreground">
                            {subText}
                        </span>
                    )}
                </span>
                <span className="flex flex-col items-end gap-1">
                    <span className="flex items-center gap-1.5">
                        <RowBadges row={row} startTime={startTime} />
                        <ChevronDown className="size-4 text-text-faint" />
                    </span>
                    {/* 誰が記録したか。開いたときだけ出す。補講と授業の記録をした人が違うときは、それぞれの段に出す */}
                    {!showsRecorderPerSection && recorderText !== null && (
                        <span className="text-[10px] text-text-faint">
                            {recorderText}
                        </span>
                    )}
                </span>
            </button>

            {/* 保護者からの連絡事項（この回の記録の連絡） */}
            {row.kind === 'regular' && row.note !== null && (
                <p className="mt-2 flex items-start gap-1.5 rounded-lg bg-background px-3 py-2 text-xs whitespace-pre-wrap">
                    <MessageSquareText className="mt-px size-3.5 shrink-0 text-muted-foreground" />
                    {row.note}
                </p>
            )}

            {/* 来る側の行：欠席した日の連絡と、補講の見出し（何の補講か・来る時間・何日の欠席分か） */}
            {row.kind === 'makeup' && (
                <>
                    <AbsenceNotes absences={row.absences} />
                    <div className="mt-2">
                        <MakeupHeading group={row} startTime={startTime} />
                    </div>
                </>
            )}

            {/* 自分のクラスのこの回に補講でも来る行：来る順に「① 補講」「② 授業」の2段で記録する */}
            {catchUp !== null && (
                <div className="mt-2">
                    <AbsenceNotes absences={catchUp.absences} />
                    <div className="mt-2">
                        <MakeupHeading
                            group={catchUp}
                            startTime={startTime}
                            prefix="① "
                            recordedBy={
                                showsRecorderPerSection
                                    ? catchUp.recordedBy
                                    : null
                            }
                        />
                    </div>
                    <div
                        className={cn(
                            'flex gap-1.5',
                            !isFuture &&
                                catchUp.makeupStatus === null &&
                                pendingAreaClass,
                        )}
                    >
                        {(['出席', '欠席'] as const).map((status) => (
                            <Button
                                key={status}
                                type="button"
                                variant="outline"
                                className={cn(
                                    'flex-1',
                                    catchUp.makeupStatus === status &&
                                        selectedClass,
                                    // まだ来ていない日は、補講の出欠は記録できない（薄く表示して、押したら理由を出す）
                                    isFuture && 'opacity-50',
                                )}
                                aria-disabled={isFuture}
                                onClick={
                                    isFuture
                                        ? notifyNotYet
                                        : () =>
                                              send(
                                                  makeup(catchUp.attendanceId)
                                                      .url,
                                                  { makeup_status: status },
                                                  // 授業の出欠がまだなら、続けて記録できるよう開いたままにする
                                                  row.kind === 'regular' &&
                                                      row.status === null,
                                              )
                                }
                            >
                                {catchUp.makeupStatus === status && <Check />}
                                {status}
                            </Button>
                        ))}
                    </div>
                    <div className="mt-2.5 mb-1.5 flex items-baseline justify-between gap-2">
                        <p className="text-xs font-bold">
                            ② 授業 {startTime}〜
                        </p>
                        {showsRecorderPerSection && recorderText !== null && (
                            <span className="shrink-0 text-[10px] text-text-faint">
                                {recorderText}
                            </span>
                        )}
                    </div>
                </div>
            )}

            <div
                className={cn(
                    'flex gap-1.5',
                    catchUp === null && row.kind === 'regular' && 'mt-2',
                    needsAttendance && pendingAreaClass,
                )}
            >
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
            {/* 補講予定の選択欄：［欠席］を押したとき、または欠席の記録の行を開いたときに出す。
                押したあとの動きで2つに分ける。
                - 補講日を選ぶ（振替・30分前補講）：補講先の授業回を選ぶモーダルに進むので、矢印（›）を付ける
                - 補講日は選ばない（あとで決める・補講しない）：その場で記録する
                どれかを選ばないと欠席が記録されないため、4つとも同じ見た目のボタンにする（控えめにすると、選ばなくてよいように見えるため） */}
            {showsMakeupOptions && (
                <div className="mt-2.5 border-t border-dashed border-border pt-2">
                    {/* 補講予定をまだ選んでいない間は、4つ全体を囲み、見出しに「まだ記録されていない」ことを出す */}
                    <div className={cn(needsMakeupChoice && pendingAreaClass)}>
                        {needsMakeupChoice && (
                            <p className="mb-2 flex items-center gap-1.5 text-xs font-bold text-brand-accent-text">
                                <CircleDashed className="size-3.5 shrink-0" />
                                補講予定を選ぶと、欠席が記録されます
                            </p>
                        )}
                        {/* 補講未定のときは、補講日を選ぶ2つだけを囲む */}
                        <div
                            className={cn(
                                needsMakeupLesson && pendingAreaClass,
                            )}
                        >
                            <p
                                className={cn(
                                    'mb-1.5 text-xs text-muted-foreground',
                                    (needsMakeupChoice || needsMakeupLesson) &&
                                        pendingLabelClass,
                                )}
                            >
                                補講日を選ぶ
                            </p>
                            <div className="grid grid-cols-2 gap-1.5">
                                {(['振替', '30分前補講'] as const).map(
                                    (makeupType) => (
                                        <Button
                                            key={makeupType}
                                            type="button"
                                            variant="outline"
                                            className={cn(
                                                isMakeupSelected(makeupType) &&
                                                    selectedClass,
                                            )}
                                            onClick={() =>
                                                setPickingMakeupType(makeupType)
                                            }
                                        >
                                            {isMakeupSelected(makeupType) && (
                                                <Check />
                                            )}
                                            {makeupType}
                                            <ChevronRight className="text-text-faint" />
                                        </Button>
                                    ),
                                )}
                            </div>
                        </div>

                        <p
                            className={cn(
                                'mt-2.5 mb-1.5 text-xs text-muted-foreground',
                                needsMakeupChoice && pendingLabelClass,
                            )}
                        >
                            補講日は選ばない
                        </p>
                        <div className="grid grid-cols-2 gap-1.5">
                            {/* ボタンは押したときの動きの言葉にする（記録後のタグは「補講未定」「補講なし」と状態の言葉で出す） */}
                            {(
                                [
                                    {
                                        makeupType: '未定',
                                        label: 'あとで決める',
                                    },
                                    {
                                        makeupType: '補講なし',
                                        label: '補講しない',
                                    },
                                ] as const
                            ).map(({ makeupType, label }) => (
                                <Button
                                    key={makeupType}
                                    type="button"
                                    variant="outline"
                                    className={cn(
                                        isMakeupSelected(makeupType) &&
                                            selectedClass,
                                    )}
                                    onClick={() => recordAbsent(makeupType)}
                                >
                                    {isMakeupSelected(makeupType) && <Check />}
                                    {label}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* 記録が消える操作の前の確認。
                - 補講先が決まっている欠席の記録で［出席］を押したとき：補講予定が消える
                - 補講先での出欠まで記録済みの欠席で、補講予定を変えたとき：補講の記録が消える */}
            <Dialog
                open={confirmation !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmation(null);
                    }
                }}
            >
                <DialogContent className="gap-3 bg-card p-4">
                    <DialogTitle className="text-base">
                        {confirmation?.title}
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
                        {confirmation?.message}
                    </DialogDescription>
                    <DialogFooter className="flex-row gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={() => setConfirmation(null)}
                        >
                            キャンセル
                        </Button>
                        <Button
                            type="button"
                            className="flex-1"
                            onClick={() => confirmation?.run()}
                        >
                            {confirmation?.confirmLabel}
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
                    otherMakeups={row.otherMakeups}
                    onSelect={recordAbsentWithMakeupLesson}
                    onClose={() => setPickingMakeupType(null)}
                />
            )}
        </li>
    );
}
