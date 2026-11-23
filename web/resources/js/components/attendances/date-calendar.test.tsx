import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import DateCalendar from '@/components/attendances/date-calendar';
import { Sheet } from '@/components/ui/sheet';
import type { CalendarData } from '@/types/attendance';

// 2026年度（2026/4〜2027/3）のカレンダーのデータ
const fiscalYear2026: CalendarData = {
    months: [
        '2026-04',
        '2026-05',
        '2026-06',
        '2026-07',
        '2026-08',
        '2026-09',
        '2026-10',
        '2026-11',
        '2026-12',
        '2027-01',
        '2027-02',
        '2027-03',
    ],
    lessonDates: ['2026-04-04', '2026-11-14', '2026-12-05'],
    weekLessonNos: {},
    holidayWeeks: {},
};

// 2025年度（2025/4〜2026/3）のカレンダーのデータ（前の年度の月に移ったときに読み込む）
const fiscalYear2025: CalendarData = {
    months: [
        '2025-04',
        '2025-05',
        '2025-06',
        '2025-07',
        '2025-08',
        '2025-09',
        '2025-10',
        '2025-11',
        '2025-12',
        '2026-01',
        '2026-02',
        '2026-03',
    ],
    lessonDates: ['2026-02-07', '2026-03-07'],
    weekLessonNos: {},
    holidayWeeks: {},
};

// カレンダーを表示する。日付のマスは押すとモーダルを閉じるため、モーダル（Sheet）の中に置く
function renderCalendar(props: {
    date: string;
    firstSelectableDate: string | null;
    lastSelectableDate: string | null;
}) {
    render(
        <Sheet open>
            <DateCalendar
                today="2026-11-22"
                calendar={fiscalYear2026}
                {...props}
            />
        </Sheet>,
    );
}

// 選べる日（押せる日付のマス）があるか
function hasSelectableDay(label: string): boolean {
    return screen.queryByRole('link', { name: label }) !== null;
}

describe('DateCalendar', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        fetchMock.mockResolvedValue(
            new Response(JSON.stringify(fiscalYear2025), {
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        cleanup();
        fetchMock.mockReset();
        vi.unstubAllGlobals();
    });

    it('年度の中の月に移るときは、読み込まずにすぐ選べる日を出す', async () => {
        const user = userEvent.setup();
        renderCalendar({
            date: '2026-11-14',
            firstSelectableDate: '2026-04-04',
            lastSelectableDate: '2026-12-05',
        });

        await user.click(screen.getByRole('button', { name: '次の月' }));

        expect(screen.getByText('2026年12月')).toBeTruthy();
        expect(hasSelectableDay('5')).toBe(true);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('前の年度の月に移るときは、その年度の分を1回だけ読み込む', async () => {
        const user = userEvent.setup();
        renderCalendar({
            date: '2026-04-04',
            firstSelectableDate: '2026-02-07',
            lastSelectableDate: '2026-12-05',
        });

        // 2026年3月（2025年度）に移ると、2025年度の分を読み込み、選べる日が出る
        await user.click(screen.getByRole('button', { name: '前の月' }));

        expect(await screen.findByRole('link', { name: '7' })).toBeTruthy();
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toContain('month=2026-03');

        // 同じ2025年度の2月に移っても、もう読み込まない
        await user.click(screen.getByRole('button', { name: '前の月' }));

        expect(screen.getByText('2026年2月')).toBeTruthy();
        expect(hasSelectableDay('7')).toBe(true);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('今年度の月は、選べる日が無くても3月まで移れる', async () => {
        const user = userEvent.setup();
        renderCalendar({
            date: '2026-11-14',
            firstSelectableDate: '2026-04-04',
            lastSelectableDate: '2026-12-05',
        });

        const next = screen.getByRole('button', { name: '次の月' });

        // 12月→1月→2月→3月
        for (let i = 0; i < 4; i++) {
            await user.click(next);
        }

        expect(screen.getByText('2027年3月')).toBeTruthy();
        // 来年度（4月）には移れない
        expect((next as HTMLButtonElement).disabled).toBe(true);
    });

    it('カレンダーの最初のマスには、月も付ける', () => {
        renderCalendar({
            date: '2026-11-14',
            firstSelectableDate: '2026-04-04',
            lastSelectableDate: '2026-12-05',
        });

        // 2026年11月のカレンダーの最初のマスは10/26（月）
        expect(screen.getByText('10/26')).toBeTruthy();
        expect(screen.getByText('11/1')).toBeTruthy();
    });
});
