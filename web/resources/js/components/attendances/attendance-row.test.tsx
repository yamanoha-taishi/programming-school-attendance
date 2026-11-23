import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';
import AttendanceRow from '@/components/attendances/attendance-row';
import type {
    AttendanceRowData,
    MakeupGroupData,
    MakeupRowData,
    RegularRowData,
} from '@/types/attendance';

// 所属する生徒の行（出席を記録済み）。必要な項目だけ上書きして使う
function regularRow(overrides: Partial<RegularRowData> = {}): RegularRowData {
    return {
        kind: 'regular',
        studentId: 1,
        name: '山本りく',
        nameKana: 'ヤマモトリク',
        schoolClassName: '応用クラス1組',
        lessonId: 10,
        lessonNo: 26,
        attendanceId: 100,
        displayStatus: '出席',
        status: '出席',
        makeupType: null,
        makeupDate: null,
        makeupLessonId: null,
        makeupSessionKey: null,
        makeupStatus: null,
        recordedBy: '佐々木 千夏',
        isNotice: false,
        note: null,
        makeupGroupTypes: [],
        otherMakeups: [],
        catchUp: null,
        ...overrides,
    };
}

// 補講の情報（10/24欠席分の30分前補講を、佐々木 千夏が「出席」と記録済み）
function makeupGroup(
    overrides: Partial<MakeupGroupData> = {},
): MakeupGroupData {
    return {
        attendanceId: 200,
        makeupTypes: ['30分前補講'],
        absences: [{ absentDate: '2026-10-24', note: null }],
        makeupStatus: '出席',
        recordedBy: '佐々木 千夏',
        ...overrides,
    };
}

function renderRow(row: AttendanceRowData, date = '2026-10-31') {
    render(
        <ul>
            <AttendanceRow
                row={row}
                makeupCandidates={[]}
                date={date}
                startTime="14:30"
                isFuture={false}
            />
        </ul>,
    );
}

// 行を開く（記録済みの行は閉じた状態で表示される）
async function openRow(name: string) {
    await userEvent
        .setup()
        .click(screen.getByRole('button', { name: new RegExp(name) }));
}

describe('AttendanceRow：記録したスタッフ', () => {
    afterEach(cleanup);

    it('補講と授業の記録が同じ人なら、名前を1つにまとめる', async () => {
        renderRow(regularRow({ catchUp: makeupGroup() }));
        await openRow('山本りく');

        expect(screen.getAllByText('記録：佐々木 千夏')).toHaveLength(1);
    });

    it('補講と授業の記録が違う人なら、それぞれの段に出す', async () => {
        renderRow(
            regularRow({
                recordedBy: '山田 秀樹',
                catchUp: makeupGroup(),
            }),
        );
        await openRow('山本りく');

        expect(screen.getAllByText('記録：佐々木 千夏')).toHaveLength(1);
        expect(screen.getAllByText('記録：山田 秀樹')).toHaveLength(1);
    });

    it('補講だけ記録済みなら、補講の段にだけ名前を出す', () => {
        // 授業の出欠がまだなので、行は開いた状態で表示される
        renderRow(
            regularRow({
                displayStatus: '未記録',
                status: null,
                attendanceId: null,
                recordedBy: null,
                catchUp: makeupGroup(),
            }),
        );

        expect(screen.getByText('① 30分前補講 14:00〜')).toBeTruthy();
        expect(screen.getAllByText(/^記録：/)).toHaveLength(1);
        expect(screen.getByText('記録：佐々木 千夏')).toBeTruthy();
    });

    it('来る側の行は、補講先での出欠を記録した人を出す', async () => {
        const row: MakeupRowData = {
            kind: 'makeup',
            studentId: 2,
            name: '石井れん',
            nameKana: 'イシイレン',
            schoolClassName: '応用クラス2組',
            lessonId: 10,
            ...makeupGroup({ makeupTypes: ['振替'] }),
        };
        renderRow(row);
        await openRow('石井れん');

        expect(screen.getByText('記録：佐々木 千夏')).toBeTruthy();
    });
});

describe('AttendanceRow：補講のタグと名前の下の日付', () => {
    afterEach(cleanup);

    it('自分のクラスの回に1時間前補講で来る生徒は、来る時間と種類のタグと、2つの欠席日を出す', () => {
        renderRow(
            regularRow({
                displayStatus: '未記録',
                status: null,
                recordedBy: null,
                catchUp: makeupGroup({
                    makeupTypes: ['30分前補講', '30分前補講'],
                    absences: [
                        { absentDate: '2026-10-17', note: null },
                        { absentDate: '2026-10-24', note: null },
                    ],
                    makeupStatus: null,
                    recordedBy: null,
                }),
            }),
        );

        expect(
            screen.getAllByText('13:30〜 1時間前補講').length,
        ).toBeGreaterThan(0);
        expect(
            screen.getAllByText('10/17・10/24欠席分').length,
        ).toBeGreaterThan(0);
    });

    it('休んだ日の行は、補講の種類のタグと、名前の下に補講日を出す', () => {
        renderRow(
            regularRow({
                displayStatus: '欠席',
                status: '欠席',
                makeupType: '30分前補講',
                makeupDate: '2026-11-21',
                makeupLessonId: 30,
                makeupSessionKey: '2026-11-21-5',
                makeupGroupTypes: ['30分前補講', '30分前補講'],
            }),
        );

        // 同じ回に30分前補講が2回分入っているので「1時間前補講」
        expect(screen.getByText('1時間前補講')).toBeTruthy();
        expect(screen.getByText('補講日 11/21(土)')).toBeTruthy();
    });
});

describe('AttendanceRow：記録が消えるときの確認', () => {
    afterEach(cleanup);

    it('1時間前補講の片方の補講予定を変えるときは、消える分と残る分を出す', async () => {
        const user = userEvent.setup();
        renderRow(
            regularRow({
                displayStatus: '欠席',
                status: '欠席',
                makeupType: '30分前補講',
                makeupDate: '2026-11-21',
                makeupLessonId: 30,
                makeupSessionKey: '2026-11-21-5',
                makeupStatus: '出席',
                makeupGroupTypes: ['30分前補講', '30分前補講'],
                otherMakeups: [
                    {
                        sessionKey: '2026-11-21-5',
                        makeupType: '30分前補講',
                        absentDate: '2026-11-14',
                    },
                ],
            }),
            '2026-11-07',
        );

        // 欠席の記録の行は、開くと補講予定の選択欄が出ている
        await openRow('山本りく');
        await user.click(screen.getByRole('button', { name: 'あとで決める' }));

        expect(
            await screen.findByText(
                '「1時間前補講済み 11/21(土)」のうち、11/7欠席分の記録が消えます。11/14欠席分は残ります。補講予定を変えますか？',
            ),
        ).toBeTruthy();
    });
});
