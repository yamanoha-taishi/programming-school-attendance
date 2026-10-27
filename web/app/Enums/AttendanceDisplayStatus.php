<?php

namespace App\Enums;

use App\Models\Attendance;

enum AttendanceDisplayStatus: string
{
    case Transferred = '振替済み';
    case CaughtUp = '30分前補講済み';
    case Absent = '欠席';
    case Late = '遅刻';
    case Present = '出席';
    case Unrecorded = '未記録';

    /**
     * 出席記録1件から、画面に表示する出席ステータスを決めて返す。
     * 上から順に判定し、最初に当てはまったものを返す。
     *
     * - 欠席で、補講先で出席済みなら「振替済み」または「30分前補講済み」
     * - それ以外の欠席は「欠席」（補講をまだ受けていない場合や、補講先に来なかった場合を含む）
     * - 出席を記録していれば「出席」（遅刻の連絡があった場合も、記録した後は遅刻したかどうかは重要ではないため）
     * - 出欠がまだ記録されておらず、遅刻の連絡があれば「遅刻」（授業前に遅刻の連絡があったことを、スタッフが事前に把握できるようにする）
     * - 出席記録が無い、または出欠も遅刻の連絡もまだ無い場合は「未記録」
     */
    public static function fromAttendance(?Attendance $attendance): self
    {
        if ($attendance === null) {
            return self::Unrecorded;
        }

        if ($attendance->status === '欠席') {
            if ($attendance->makeup_status === '出席') {
                return match ($attendance->makeup_type) {
                    '振替' => self::Transferred,
                    '30分前補講' => self::CaughtUp,
                    default => self::Absent,
                };
            }

            return self::Absent;
        }

        if ($attendance->status === '出席') {
            return self::Present;
        }

        return $attendance->is_late ? self::Late : self::Unrecorded;
    }
}
