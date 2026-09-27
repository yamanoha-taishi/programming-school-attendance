<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Staff;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class AttendanceSeeder extends Seeder
{
    // ---- 過去の授業回（出席結果が確定している記録） ----

    // 欠席になる確率（残りが出席）
    private const ABSENCE_RATE = 10;

    // 出席のうち遅刻になる確率
    private const LATE_RATE = 10;

    // 欠席時のmakeup_typeの重み（合計100）
    private const MAKEUP_TYPE_WEIGHTS = [
        '振替' => 20,
        '30分前補講' => 40,
        '補講なし' => 10,
        '未定' => 30,
    ];

    // 直近何日分を「まだスタッフ未対応の可能性がある」とみなすか
    private const RECENT_DAYS = 3;

    // 直近の保護者からの事前連絡（欠席・遅刻）がスタッフ未対応（staff_id = null）のままになる確率
    private const UNPROCESSED_RATE = 50;

    // ---- 今日以降の授業回（保護者からの事前連絡のみ） ----

    // 今日から何日先までの授業回に、事前連絡を作るか
    private const NOTICE_DAYS = 14;

    // 今日以降の授業回1件につき、保護者から事前連絡が来ている確率
    private const NOTICE_RATE = 20;

    // 事前連絡のうち欠席連絡になる確率（残りが遅刻連絡）
    private const NOTICE_ABSENCE_RATE = 60;

    // 事前連絡の欠席時のmakeup_typeの重み（合計100）。
    // 保護者の欠席・遅刻・補講登録画面の選択肢は「振替」「30分前補講」「未定」の3つ（要件定義書5.2）で、
    // 「補講なし」は選べないため含めない
    private const NOTICE_MAKEUP_TYPE_WEIGHTS = [
        '振替' => 30,
        '30分前補講' => 40,
        '未定' => 30,
    ];

    // 事前連絡がスタッフ未対応（staff_id = null）のままになっている確率
    private const NOTICE_UNPROCESSED_RATE = 70;

    // ---- 共通 ----

    // 欠席・遅刻時にnoteを残す確率
    private const NOTE_RATE = 30;

    private const ABSENCE_NOTES = [
        '体調不良のため欠席します',
        '発熱のため欠席します',
        '家庭の用事のため欠席します',
        '学校行事と重なったため欠席します',
        '交通機関の乱れのため欠席します',
    ];

    private const LATE_NOTES = [
        '電車遅延のため遅刻します',
        '学校が長引いたため遅刻します',
        '体調不良のため少し遅れます',
        '準備が遅れて遅刻します',
    ];

    // 乱数のシード値。値を固定することで、何度実行しても同じ順番で同じ乱数が出る
    // （本番〈デモ環境〉でもFakerを使わずに、毎回同じ出席データを作るため）。
    // ただし対象になる授業回（過去・今日以降）は実行日によって変わるため、実行日が同じ場合に同じ結果になる
    private const RANDOM_SEED = 20260406;

    private Randomizer $random;

    private Carbon $today;

    private Carbon $recentCutoff;

    private Carbon $noticeUntil;

    /** @var int[] */
    private array $staffIds = [];

    // Eloquent\Collection<TKey, TModel>はTModelがModelのサブクラスであることを
    // 要求するため、「グループの入れ物」である外側のコレクションをEloquent\Collection
    // として宣言することはできない（中身がCollectionであってModelではないため）。
    // そのため$lessonsをgroupBy()する前にcollect()でSupport\Collectionへ変換し、
    // 外側・内側とも実際にSupport\Collectionが代入されるようにしている。

    /**
     * 「section_id-school_class_id」 => その生徒が受ける授業回（日付順）。
     * 授業実施単位「B」はB1〜B6の6クラスで共用されており、同じ日に同じsectionの授業回が
     * クラスごとに存在するため、sectionだけでなく授業クラスでも絞り込む必要がある。
     *
     * @var Collection<int|string, Collection<int, Lesson>>
     */
    private Collection $lessonsByClassSection;

    /** @var Collection<int|string, Collection<int, Lesson>> lesson_plan_id => lessons（日付順） */
    private Collection $lessonsByPlan;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $this->today = Carbon::today();
        $this->recentCutoff = $this->today->copy()->subDays(self::RECENT_DAYS);
        $this->noticeUntil = $this->today->copy()->addDays(self::NOTICE_DAYS);

        // 乱数を呼ぶ順番が毎回同じになるよう、取得するデータには必ず並び順を指定する
        $this->staffIds = Staff::orderBy('id')->pluck('id')->all();

        // 授業回がどの授業クラスのものかはlesson_plans.school_class_idで決まるため、joinして一緒に取得する。
        // collect()でEloquent\CollectionからSupport\Collectionに変換してからgroupBy()する
        // （Eloquent\Collectionのままgroupby()すると、外側の「グループの入れ物」も
        // Eloquent\Collectionのまま返ってきてしまい、上記のとおり型として不正になる）。
        $lessons = collect(
            Lesson::join('lesson_plans', 'lesson_plans.id', '=', 'lessons.lesson_plan_id')
                ->orderBy('lessons.date')
                ->orderBy('lessons.id')
                ->get([
                    'lessons.id',
                    'lessons.lesson_plan_id',
                    'lessons.section_id',
                    'lessons.date',
                    'lesson_plans.school_class_id',
                ])
        );

        $this->lessonsByClassSection = $lessons->groupBy(
            fn (Lesson $lesson) => $this->classSectionKey($lesson->section_id, (int) $lesson->getAttribute('school_class_id'))
        );
        $this->lessonsByPlan = $lessons->groupBy('lesson_plan_id');

        Student::orderBy('id')->get()->each(function (Student $student) {
            $key = $this->classSectionKey($student->section_id, $student->school_class_id);

            foreach ($this->lessonsByClassSection->get($key, collect()) as $lesson) {
                if ($lesson->date->gt($this->noticeUntil)) {
                    break;
                }

                if ($this->isOnLeave($student, $lesson->date)) {
                    continue;
                }

                if ($lesson->date->lt($this->today)) {
                    Attendance::create($this->buildPastAttributes($student, $lesson));

                    continue;
                }

                if ($this->chance(self::NOTICE_RATE)) {
                    Attendance::create($this->buildNoticeAttributes($student, $lesson));
                }
            }
        });
    }

    private function classSectionKey(int $sectionId, int $schoolClassId): string
    {
        return "{$sectionId}-{$schoolClassId}";
    }

    /**
     * 授業日が生徒の休会期間（leave_from〜leave_until）に重なっているか。
     * leave_untilがNULLの場合はleave_from以降ずっと休会中として扱う。
     */
    private function isOnLeave(Student $student, CarbonInterface $lessonDate): bool
    {
        if ($student->leave_from === null) {
            return false;
        }

        if ($lessonDate->lt($student->leave_from)) {
            return false;
        }

        if ($student->leave_until === null) {
            return true;
        }

        return $lessonDate->lte($student->leave_until);
    }

    /**
     * 過去の授業回の記録（出席・欠席の結果が確定している）。
     *
     * @return array<string, mixed>
     */
    private function buildPastAttributes(Student $student, Lesson $lesson): array
    {
        $isAbsent = $this->chance(self::ABSENCE_RATE);
        $status = $isAbsent ? '欠席' : '出席';
        $isLate = ! $isAbsent && $this->chance(self::LATE_RATE);

        [$makeupType, $makeupLessonId] = $isAbsent
            ? $this->pickMakeup($student, $lesson, self::MAKEUP_TYPE_WEIGHTS)
            : [null, null];

        // 保護者からの事前連絡（欠席連絡・遅刻連絡）に該当するか。
        // 普通に出席しただけの記録は、保護者の事前連絡を経由しないので対象外。
        $guardianNotified = $isAbsent || $isLate;
        $isRecent = $lesson->date->gte($this->recentCutoff);

        $staffId = ($isRecent && $guardianNotified && $this->chance(self::UNPROCESSED_RATE))
            ? null
            : $this->pick($this->staffIds);

        return [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'makeup_lesson_id' => $makeupLessonId,
            'staff_id' => $staffId,
            'status' => $status,
            'is_late' => $isLate,
            'makeup_type' => $makeupType,
            'note' => $guardianNotified ? $this->pickNote($isAbsent) : null,
        ];
    }

    /**
     * 今日以降の授業回への、保護者からの事前連絡（欠席連絡・遅刻連絡）。
     * 授業はまだ行われていないため、遅刻連絡のstatusはnull（未定）のままにする。
     *
     * @return array<string, mixed>
     */
    private function buildNoticeAttributes(Student $student, Lesson $lesson): array
    {
        $isAbsent = $this->chance(self::NOTICE_ABSENCE_RATE);

        [$makeupType, $makeupLessonId] = $isAbsent
            ? $this->pickMakeup($student, $lesson, self::NOTICE_MAKEUP_TYPE_WEIGHTS)
            : [null, null];

        $staffId = $this->chance(self::NOTICE_UNPROCESSED_RATE)
            ? null
            : $this->pick($this->staffIds);

        return [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'makeup_lesson_id' => $makeupLessonId,
            'staff_id' => $staffId,
            'status' => $isAbsent ? '欠席' : null,
            'is_late' => ! $isAbsent,
            'makeup_type' => $makeupType,
            'note' => $this->pickNote($isAbsent),
        ];
    }

    /**
     * 欠席時の補講方法（makeup_type）と補講先の授業回（makeup_lesson_id）を決める。
     * 振替・30分前補講の補講先が見つからなかった場合は「未定」に倒す。
     *
     * @param  array<string, int>  $weights
     * @return array{0: string, 1: int|null}
     */
    private function pickMakeup(Student $student, Lesson $lesson, array $weights): array
    {
        $makeupType = $this->pickWeighted($weights);

        $makeupLessonId = match ($makeupType) {
            '振替' => $this->pickTransferLessonId($lesson),
            '30分前補講' => $this->pickCatchUpLessonId($student, $lesson),
            default => null,
        };

        if (in_array($makeupType, ['振替', '30分前補講'], true) && $makeupLessonId === null) {
            $makeupType = '未定';
        }

        return [$makeupType, $makeupLessonId];
    }

    /**
     * 振替先：同じlesson_plan_id（＝同じ内容）で、別のsectionのlessonから選ぶ。
     * 過去の欠席なら過去・今日以降のどちらでもよいが、今日以降の事前連絡の場合は
     * 過去の授業回には振り替えられないため、今日以降の授業回に限る。
     */
    private function pickTransferLessonId(Lesson $lesson): ?int
    {
        $candidates = $this->lessonsByPlan->get($lesson->lesson_plan_id, collect())
            ->where('section_id', '!=', $lesson->section_id);

        if ($lesson->date->gte($this->today)) {
            $candidates = $candidates->filter(fn (Lesson $candidate) => $candidate->date->gte($this->today));
        }

        return $candidates->isEmpty() ? null : $this->pick($candidates->all())->id;
    }

    /**
     * 30分前補講先：本人の授業クラス・sectionで、欠席した回の次の授業回を選ぶ。
     * 過去の欠席でも、次の授業回が今日以降なら「補講予定」として今日以降の授業回が選ばれる。
     */
    private function pickCatchUpLessonId(Student $student, Lesson $lesson): ?int
    {
        $key = $this->classSectionKey($student->section_id, $student->school_class_id);

        $next = $this->lessonsByClassSection->get($key, collect())
            ->first(fn (Lesson $candidate) => $candidate->date->gt($lesson->date));

        return $next?->id;
    }

    /**
     * 欠席・遅刻の連絡事項（note）を、NOTE_RATEの確率で選ぶ。
     */
    private function pickNote(bool $isAbsent): ?string
    {
        if (! $this->chance(self::NOTE_RATE)) {
            return null;
        }

        return $isAbsent
            ? $this->pick(self::ABSENCE_NOTES)
            : $this->pick(self::LATE_NOTES);
    }

    /**
     * 重み付きで1つ選ぶ（重みの合計は100）。
     *
     * @param  array<string, int>  $weights
     */
    private function pickWeighted(array $weights): string
    {
        $roll = $this->random->getInt(1, 100);
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;

            if ($roll <= $cumulative) {
                return $value;
            }
        }

        return '未定';
    }

    /**
     * $percent％の確率でtrueを返す。
     */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * 配列からランダムに1つ選んで返す。
     * キーが0始まりの連番でない配列（Collection::where()の結果など）にも対応するため、
     * 値ではなくキーを抽選してから取り出している。
     * 空の配列からは選べないため、空の場合は例外にする（呼び出し側の前提が崩れていることを早めに気づけるように）。
     *
     * @template T
     *
     * @param  array<array-key, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        if ($items === []) {
            throw new LogicException('空の配列からは選べません。');
        }

        $key = $this->random->pickArrayKeys($items, 1)[0];

        return $items[$key];
    }
}
