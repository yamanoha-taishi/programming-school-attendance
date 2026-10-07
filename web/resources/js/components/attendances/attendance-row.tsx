import { router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import StatusBadge from '@/components/attendances/status-badge';
import { Button } from '@/components/ui/button';
import { makeup, update } from '@/routes/admin/attendances';
import type { AttendanceRowData } from '@/types/attendance';

// 補講の種類ごとの色（淡い背景＋同系統の文字色）
const makeupTypeColors: Record<string, string> = {
    振替: 'bg-status-transfer-bg text-status-transfer-text',
    '30分前補講': 'bg-status-makeup-bg text-status-makeup',
};

// "2026-10-01" → "10/1"
function formatMonthDay(date: string): string {
    const [, month, day] = date.split('-');

    return `${Number(month)}/${Number(day)}`;
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

    return (
        <span className="rounded-full bg-status-makeup-bg px-2 py-0.5 text-xs font-semibold text-status-makeup">
            補講あり
        </span>
    );
}

// 行の右側に出すバッジ
function RowBadges({ row }: { row: AttendanceRowData }) {
    if (row.kind === 'regular') {
        return <StatusBadge status={row.displayStatus} />;
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

export default function AttendanceRow({ row }: { row: AttendanceRowData }) {
    const [isOpen, setIsOpen] = useState(!isRecorded(row));

    // 送信して、保存できたら行を折りたたむ
    function send(url: string, data: Record<string, string>) {
        router.patch(url, data, {
            preserveScroll: true, // 画面の一番上に戻らないようにする
            onSuccess: () => setIsOpen(false), // 保存できたら行を折りたたむ
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

    // ［欠席］：来る側の行は補講先での欠席を記録する（通常の行はG-3で作る）
    function handleAbsent() {
        if (row.kind === 'makeup') {
            send(makeup(row.attendanceId).url, { makeup_status: '欠席' });
        }
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
                    className="flex-1"
                    onClick={handleAbsent}
                >
                    欠席
                </Button>
            </div>
        </li>
    );
}
