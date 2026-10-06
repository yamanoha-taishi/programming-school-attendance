const colors: Record<string, string> = {
    通常出席: 'bg-status-attend-bg text-status-attend-text',
    遅刻: 'bg-status-late-bg text-status-late-text',
    欠席: 'bg-status-absent-bg text-status-absent-text',
    振替済み: 'bg-status-transfer-bg text-status-transfer-text',
    '30分前補講済み': 'bg-status-makeup-bg text-status-makeup',
    未定: 'bg-status-pending-bg text-status-pending-text',
};

export default function StatusBadge({ status }: { status: string }) {
    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${colors[status] ?? colors['未定']}`}
        >
            {status}
        </span>
    );
}
