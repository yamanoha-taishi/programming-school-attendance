import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import MakeupLessonSheet from '@/components/attendances/makeup-lesson-sheet';
import type { MakeupCandidate, OtherMakeup } from '@/types/attendance';

// 補講先の候補（応用クラス 14:30-16:00）
function candidate(lessonId: number, date: string): MakeupCandidate {
    return {
        lessonId,
        sessionKey: `${date}-5`,
        date,
        weekday: '土',
        startTime: '14:30',
        endTime: '16:00',
        title: '応用クラス',
        lessonNo: 27,
        sameContent: false,
    };
}

const candidates = [
    candidate(1, '2026-11-14'),
    candidate(2, '2026-11-21'),
    candidate(3, '2026-11-28'),
];

function renderSheet(
    makeupType: '振替' | '30分前補講',
    otherMakeups: OtherMakeup[],
) {
    render(
        <MakeupLessonSheet
            makeupType={makeupType}
            absence={{ name: '中村そら', date: '2026-11-07', lessonNo: 26 }}
            candidates={candidates}
            otherMakeups={otherMakeups}
            onSelect={vi.fn()}
            onClose={vi.fn()}
        />,
    );
}

// その候補のボタン（日付で探す）
function candidateButton(dateLabel: string): HTMLButtonElement {
    const button = screen.getByText(dateLabel).closest('button');

    if (button === null) {
        throw new Error(`${dateLabel}の候補がありません`);
    }

    return button;
}

describe('MakeupLessonSheet：ほかの欠席分の補講が入っている回', () => {
    afterEach(cleanup);

    it('30分前補講がもう1回分入っている回は、1時間前補講になることと来る時間を出す', () => {
        renderSheet('30分前補講', [
            {
                sessionKey: '2026-11-21-5',
                makeupType: '30分前補講',
                absentDate: '2026-10-31',
            },
        ]);

        const button = candidateButton('11/21(土)');

        expect(button.disabled).toBe(false);
        expect(button.textContent).toContain('1時間前補講になります');
        expect(button.textContent).toContain('来る時間 13:30');
        // ほかの回は、ふつうの30分前補講
        expect(candidateButton('11/14(土)').textContent).toContain(
            '来る時間 14:00',
        );
    });

    it('30分前補講が2回分入っている回は、押せない', () => {
        renderSheet('30分前補講', [
            {
                sessionKey: '2026-11-28-5',
                makeupType: '30分前補講',
                absentDate: '2026-10-24',
            },
            {
                sessionKey: '2026-11-28-5',
                makeupType: '30分前補講',
                absentDate: '2026-10-31',
            },
        ]);

        const button = candidateButton('11/28(土)');

        expect(button.disabled).toBe(true);
        expect(button.textContent).toContain('2回分の30分前補講が入っています');
    });

    it('振替が入っている回に振替は選べず、30分前補講なら「振替＋30分前補講」になる', () => {
        const otherMakeups = [
            {
                sessionKey: '2026-11-14-5',
                makeupType: '振替',
                absentDate: '2026-10-31',
            },
        ];

        renderSheet('振替', otherMakeups);
        expect(candidateButton('11/14(土)').disabled).toBe(true);
        cleanup();

        renderSheet('30分前補講', otherMakeups);
        const button = candidateButton('11/14(土)');

        expect(button.disabled).toBe(false);
        expect(button.textContent).toContain('振替＋30分前補講になります');
        expect(button.textContent).toContain('来る時間 14:00');
    });
});
