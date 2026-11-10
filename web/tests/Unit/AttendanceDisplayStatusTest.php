<?php

namespace Tests\Unit;

use App\Enums\AttendanceDisplayStatus;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 出席記録1件から、画面に表示する出席ステータスを正しく決められるかのテスト。
 * DBには保存せず、new Attendance([...])で作った記録だけを使う。
 */
class AttendanceDisplayStatusTest extends TestCase
{
    public function test_unrecorded_when_there_is_no_attendance_record()
    {
        $this->assertSame(
            AttendanceDisplayStatus::Unrecorded,
            AttendanceDisplayStatus::fromAttendance(null),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('attendanceCases')]
    public function test_display_status_is_determined_from_attendance(
        array $attributes,
        AttendanceDisplayStatus $expected,
    ) {
        $attendance = new Attendance($attributes);

        $this->assertSame($expected, AttendanceDisplayStatus::fromAttendance($attendance));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: AttendanceDisplayStatus}>
     */
    public static function attendanceCases(): array
    {
        return [
            // ---- 未記録 ----
            '記録はあるが、出欠も遅刻の連絡もまだ無い' => [
                ['status' => null, 'is_late' => false],
                AttendanceDisplayStatus::Unrecorded,
            ],

            // ---- 出席・遅刻 ----
            '時間通りに出席した' => [
                ['status' => '出席', 'is_late' => false],
                AttendanceDisplayStatus::Present,
            ],
            '遅刻の連絡があり、出席を記録した' => [
                ['status' => '出席', 'is_late' => true],
                AttendanceDisplayStatus::Present,
            ],
            '授業前に遅刻の連絡があり、出欠はまだ記録されていない' => [
                ['status' => null, 'is_late' => true],
                AttendanceDisplayStatus::Late,
            ],

            // ---- 欠席 ----
            '欠席した（補講の予定なし）' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => null],
                AttendanceDisplayStatus::Absent,
            ],
            '欠席した（補講なし）' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '補講なし'],
                AttendanceDisplayStatus::Absent,
            ],
            '欠席した（補講方法は未定）' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '未定'],
                AttendanceDisplayStatus::Absent,
            ],
            '遅刻の連絡の後、結局欠席した' => [
                ['status' => '欠席', 'is_late' => true],
                AttendanceDisplayStatus::Absent,
            ],

            // ---- 振替 ----
            '振替を予定しているが、まだ受けていない' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '振替', 'makeup_status' => null],
                AttendanceDisplayStatus::Absent,
            ],
            '振替先で出席した' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '振替', 'makeup_status' => '出席'],
                AttendanceDisplayStatus::Transferred,
            ],
            '振替先に来なかった' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '振替', 'makeup_status' => '欠席'],
                AttendanceDisplayStatus::Absent,
            ],

            // ---- 30分前補講 ----
            '30分前補講を予定しているが、まだ受けていない' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '30分前補講', 'makeup_status' => null],
                AttendanceDisplayStatus::Absent,
            ],
            '30分前補講を受けた' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '30分前補講', 'makeup_status' => '出席'],
                AttendanceDisplayStatus::CaughtUp,
            ],
            '30分前補講に来なかった' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '30分前補講', 'makeup_status' => '欠席'],
                AttendanceDisplayStatus::Absent,
            ],

            // ---- 想定外のデータ ----
            // 補講方法が振替・30分前補講以外なのに補講先の出席結果がある場合は、「済み」にしない
            '補講方法が未定なのに、補講先の出席結果が入っている' => [
                ['status' => '欠席', 'is_late' => false, 'makeup_type' => '未定', 'makeup_status' => '出席'],
                AttendanceDisplayStatus::Absent,
            ],
        ];
    }
}
