import { describe, expect, it } from 'vitest';
import { absentDatesLabel, arrivalTime, makeupLabel } from '@/lib/makeup';

describe('makeupLabel：同じ生徒が同じ回に来る補講の名前', () => {
    it('1回分はそのままの名前', () => {
        expect(makeupLabel(['振替'])).toBe('振替');
        expect(makeupLabel(['30分前補講'])).toBe('30分前補講');
    });

    it('30分前補講2回分は「1時間前補講」', () => {
        expect(makeupLabel(['30分前補講', '30分前補講'])).toBe('1時間前補講');
    });

    it('振替と30分前補講は「＋」でつなぐ', () => {
        expect(makeupLabel(['振替', '30分前補講'])).toBe('振替＋30分前補講');
        expect(makeupLabel(['振替', '30分前補講', '30分前補講'])).toBe(
            '振替＋1時間前補講',
        );
    });
});

describe('arrivalTime：実際に来てもらう時間', () => {
    it('30分前補講1回分ごとに、授業の開始時刻から30分早める', () => {
        expect(arrivalTime('14:30', ['30分前補講'])).toBe('14:00');
        expect(arrivalTime('14:30', ['30分前補講', '30分前補講'])).toBe(
            '13:30',
        );
        expect(arrivalTime('10:00', ['振替', '30分前補講'])).toBe('09:30');
    });

    it('30分前補講が無ければnull', () => {
        expect(arrivalTime('14:30', ['振替'])).toBeNull();
    });
});

describe('absentDatesLabel：何日の欠席分か', () => {
    it('日付を「・」でつなぐ', () => {
        expect(absentDatesLabel(['2026-11-07', '2026-11-14'])).toBe(
            '11/7・11/14欠席分',
        );
    });
});
