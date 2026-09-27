<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class FamilySeeder extends Seeder
{
    private const SECTION_CAPACITY = 6;

    /** @var array<string, int> school_classes.code => id */
    private array $schoolClassIdByCode = [];

    /** @var array<string, int> 「名前-曜日」 => section_id */
    private array $sectionIdByKey = [];

    /** @var array<int, int> section_id => 登録済みの生徒数 */
    private array $studentCountBySection = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->schoolClassIdByCode = SchoolClass::pluck('id', 'code')->all();

        // bcryptは重い処理なので、全員分を1回のハッシュ化で済ませる
        $password = Hash::make('password');

        foreach ($this->families() as $family) {
            $guardian = Guardian::create([...$family['guardian'], 'password' => $password]);

            foreach ($family['students'] as $student) {
                [$sectionName, $weekday] = $student['section'];
                $sectionId = $this->sectionIdFor($sectionName, $weekday);
                $this->countSeat($sectionId, $sectionName, $weekday);

                Student::create([
                    'guardian_id' => $guardian->id,
                    'school_class_id' => $this->schoolClassIdByCode[$student['class']],
                    'section_id' => $sectionId,
                    'grade' => $student['grade'],
                    'gender' => $student['gender'],
                    'name' => $student['name'],
                    'name_kana' => $student['name_kana'],
                    'note' => $student['note'] ?? null,
                    ...$this->leavePeriod($student['leave'] ?? null),
                ]);
            }
        }
    }

    /**
     * 授業実施単位の名前（A1／A2／A3／B）と曜日から、section_idを返す。
     */
    private function sectionIdFor(string $name, string $weekday): int
    {
        $key = "{$name}-{$weekday}";

        if (! isset($this->sectionIdByKey[$key])) {
            $sectionId = Section::where('name', $name)
                ->where('weekday', $weekday)
                ->orderBy('start_time')
                ->value('id');

            if ($sectionId === null) {
                throw new RuntimeException("授業実施単位が見つかりません：{$name}（{$weekday}）");
            }

            $this->sectionIdByKey[$key] = (int) $sectionId;
        }

        return $this->sectionIdByKey[$key];
    }

    /**
     * 固定データの書き間違いで定員（6名）を超えていないかを確認しながら、人数を数える。
     */
    private function countSeat(int $sectionId, string $name, string $weekday): void
    {
        $this->studentCountBySection[$sectionId] = ($this->studentCountBySection[$sectionId] ?? 0) + 1;

        if ($this->studentCountBySection[$sectionId] > self::SECTION_CAPACITY) {
            $capacity = self::SECTION_CAPACITY;

            throw new RuntimeException("授業実施単位の定員（{$capacity}名）を超えています：{$name}（{$weekday}）");
        }
    }

    /**
     * 休会期間を、Seederの実行日を基準にした相対的な日付で返す。
     * 固定の日付で書くと、時間が経つと「休会中」ではなくなってしまうため。
     *
     * - current：先月の1日から休会中（終了月未定）
     * - upcoming：来月の1日〜来月の末日まで休会予定
     *
     * @return array{leave_from: string|null, leave_until: string|null}
     */
    private function leavePeriod(?string $leave): array
    {
        return match ($leave) {
            'current' => [
                'leave_from' => Carbon::today()->startOfMonth()->subMonth()->toDateString(),
                'leave_until' => null,
            ],
            'upcoming' => [
                'leave_from' => Carbon::today()->startOfMonth()->addMonth()->toDateString(),
                'leave_until' => Carbon::today()->startOfMonth()->addMonth()->endOfMonth()->toDateString(),
            ],
            default => [
                'leave_from' => null,
                'leave_until' => null,
            ],
        };
    }

    // デモ用の保護者・生徒（固定データ）。本番（デモ環境）でも同じデータになるよう、Faker・Factoryは使わない。
    //
    // 授業クラスの割り当て：
    //   A1＝年中・年長、A2＝小1、A3＝小2（学年で決まる）
    //   B1〜B6はデモ用に学年で割り当てている（B1＝小3、B2＝小4、B3＝小5、B4＝小6、B5＝中1、B6＝中2・中3）
    //
    // 授業実施単位（section）はIDではなく「名前＋曜日」で指定し、run()の中でIDを引く。
    // 曜日ごとの人数：月3／火3／水3／木2／金3／土14（土曜Bは定員6名ちょうど）
    //
    // デモで見せたい状況：
    //   - きょうだい：佐藤（兄妹）、田中（姉妹）、山本（3人兄弟）、森（姉妹）、清水（兄弟）
    //   - メール未登録の保護者：0003・0010・0017（パスワード再設定の申請画面で問い合わせ案内が出るケース）
    //   - 休会中の生徒：藤田しおん（先月から休会中・終了月未定）
    //   - 休会予定の生徒：池田あさひ（来月のみ休会）
    //   - 性別未設定の生徒：藤田しおん
    //
    // 0001（佐藤）をデモ用の保護者ログインアカウントとして案内する想定（土曜に兄妹2人が通室）。
    //
    // 要素ごとに持っているキー（note・leaveの有無など）が違うため、型を明示しないとPHPStanが
    // 配列の形を推論しきれず「Offset 'name' might not exist」のようなエラーになる。
    // 定数の@varはPHPStanが採用しない（定数の値そのものから型を推論する）ため、
    // メソッドの戻り値として返し、@returnで形を宣言している。
    /**
     * @return list<array{
     *     guardian: array{member_code: string, name: string, name_kana: string, email: string|null, note: string|null},
     *     students: list<array{
     *         name: string,
     *         name_kana: string,
     *         gender: 'male'|'female'|null,
     *         grade: string,
     *         class: string,
     *         section: array{string, string},
     *         note?: string,
     *         leave?: 'current'|'upcoming',
     *     }>,
     * }>
     */
    private function families(): array
    {
        return [
            [
                'guardian' => ['member_code' => '0001', 'name' => '佐藤 直子', 'name_kana' => 'サトウ ナオコ', 'email' => 'guardian0001@example.com', 'note' => 'きょうだいで通室中'],
                'students' => [
                    ['name' => '佐藤はると', 'name_kana' => 'サトウハルト', 'gender' => 'male', 'grade' => '小4', 'class' => 'B2', 'section' => ['B', '土']],
                    ['name' => '佐藤ひな', 'name_kana' => 'サトウヒナ', 'gender' => 'female', 'grade' => '年長', 'class' => 'A1', 'section' => ['A1', '土']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0002', 'name' => '鈴木 大輔', 'name_kana' => 'スズキ ダイスケ', 'email' => 'guardian0002@example.com', 'note' => null],
                'students' => [
                    ['name' => '鈴木そら', 'name_kana' => 'スズキソラ', 'gender' => 'male', 'grade' => '小3', 'class' => 'B1', 'section' => ['B', '月']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0003', 'name' => '高橋 智子', 'name_kana' => 'タカハシ トモコ', 'email' => null, 'note' => '平日日中は仕事のため電話に出られないことが多いです'],
                'students' => [
                    ['name' => '高橋あおい', 'name_kana' => 'タカハシアオイ', 'gender' => 'female', 'grade' => '小1', 'class' => 'A2', 'section' => ['A2', '月']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0004', 'name' => '田中 健太', 'name_kana' => 'タナカ ケンタ', 'email' => 'guardian0004@example.com', 'note' => 'きょうだいで通室中'],
                'students' => [
                    ['name' => '田中さくら', 'name_kana' => 'タナカサクラ', 'gender' => 'female', 'grade' => '小5', 'class' => 'B3', 'section' => ['B', '水']],
                    ['name' => '田中めい', 'name_kana' => 'タナカメイ', 'gender' => 'female', 'grade' => '小2', 'class' => 'A3', 'section' => ['A3', '火']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0005', 'name' => '伊藤 由美', 'name_kana' => 'イトウ ユミ', 'email' => 'guardian0005@example.com', 'note' => null],
                'students' => [
                    ['name' => '伊藤ゆうま', 'name_kana' => 'イトウユウマ', 'gender' => 'male', 'grade' => '中1', 'class' => 'B5', 'section' => ['B', '月'], 'note' => 'プログラミング経験があり、進度が早め'],
                ],
            ],
            [
                'guardian' => ['member_code' => '0006', 'name' => '渡辺 和也', 'name_kana' => 'ワタナベ カズヤ', 'email' => 'guardian0006@example.com', 'note' => null],
                'students' => [
                    ['name' => '渡辺いつき', 'name_kana' => 'ワタナベイツキ', 'gender' => 'male', 'grade' => '年中', 'class' => 'A1', 'section' => ['A1', '火'], 'note' => '人見知りのため、最初は保護者同伴で参加'],
                ],
            ],
            [
                'guardian' => ['member_code' => '0007', 'name' => '山本 恵子', 'name_kana' => 'ヤマモト ケイコ', 'email' => 'guardian0007@example.com', 'note' => 'きょうだい3人で通室中'],
                'students' => [
                    ['name' => '山本りく', 'name_kana' => 'ヤマモトリク', 'gender' => 'male', 'grade' => '中2', 'class' => 'B6', 'section' => ['B', '土']],
                    ['name' => '山本かいと', 'name_kana' => 'ヤマモトカイト', 'gender' => 'male', 'grade' => '小3', 'class' => 'B1', 'section' => ['B', '土']],
                    ['name' => '山本そうた', 'name_kana' => 'ヤマモトソウタ', 'gender' => 'male', 'grade' => '年長', 'class' => 'A1', 'section' => ['A1', '土']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0008', 'name' => '中村 拓也', 'name_kana' => 'ナカムラ タクヤ', 'email' => 'guardian0008@example.com', 'note' => null],
                'students' => [
                    ['name' => '中村こはる', 'name_kana' => 'ナカムラコハル', 'gender' => 'female', 'grade' => '年長', 'class' => 'A1', 'section' => ['A1', '火']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0009', 'name' => '小川 美穂', 'name_kana' => 'オガワ ミホ', 'email' => 'guardian0009@example.com', 'note' => '連絡は電話よりメール希望'],
                'students' => [
                    ['name' => '小川ゆな', 'name_kana' => 'オガワユナ', 'gender' => 'female', 'grade' => '小1', 'class' => 'A2', 'section' => ['A2', '水']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0010', 'name' => '石井 翔太', 'name_kana' => 'イシイ ショウタ', 'email' => null, 'note' => null],
                'students' => [
                    ['name' => '石井れん', 'name_kana' => 'イシイレン', 'gender' => 'male', 'grade' => '小6', 'class' => 'B4', 'section' => ['B', '水']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0011', 'name' => '森 綾', 'name_kana' => 'モリ アヤ', 'email' => 'guardian0011@example.com', 'note' => 'きょうだいで通室中'],
                'students' => [
                    ['name' => '森つむぎ', 'name_kana' => 'モリツムギ', 'gender' => 'female', 'grade' => '小4', 'class' => 'B2', 'section' => ['B', '木']],
                    ['name' => '森ほのか', 'name_kana' => 'モリホノカ', 'gender' => 'female', 'grade' => '小1', 'class' => 'A2', 'section' => ['A2', '金']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0012', 'name' => '池田 裕介', 'name_kana' => 'イケダ ユウスケ', 'email' => 'guardian0012@example.com', 'note' => null],
                'students' => [
                    ['name' => '池田あさひ', 'name_kana' => 'イケダアサヒ', 'gender' => 'male', 'grade' => '中3', 'class' => 'B6', 'section' => ['B', '木'], 'note' => '受験準備のため来月は休会予定', 'leave' => 'upcoming'],
                ],
            ],
            [
                'guardian' => ['member_code' => '0013', 'name' => '橋本 麻美', 'name_kana' => 'ハシモト アサミ', 'email' => 'guardian0013@example.com', 'note' => 'お迎えは祖母が担当することがあります'],
                'students' => [
                    ['name' => '橋本みお', 'name_kana' => 'ハシモトミオ', 'gender' => 'female', 'grade' => '年中', 'class' => 'A1', 'section' => ['A1', '金']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0014', 'name' => '阿部 陽子', 'name_kana' => 'アベ ヨウコ', 'email' => 'guardian0014@example.com', 'note' => null],
                'students' => [
                    ['name' => '阿部たいち', 'name_kana' => 'アベタイチ', 'gender' => 'male', 'grade' => '小2', 'class' => 'A3', 'section' => ['A3', '金'], 'note' => 'タイピングがまだ苦手なので個別サポートが必要'],
                ],
            ],
            [
                'guardian' => ['member_code' => '0015', 'name' => '清水 学', 'name_kana' => 'シミズ マナブ', 'email' => 'guardian0015@example.com', 'note' => 'きょうだいで通室中'],
                'students' => [
                    ['name' => '清水けんと', 'name_kana' => 'シミズケント', 'gender' => 'male', 'grade' => '小5', 'class' => 'B3', 'section' => ['B', '土']],
                    ['name' => '清水はやと', 'name_kana' => 'シミズハヤト', 'gender' => 'male', 'grade' => '小2', 'class' => 'A3', 'section' => ['A3', '土']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0016', 'name' => '山崎 香織', 'name_kana' => 'ヤマザキ カオリ', 'email' => 'guardian0016@example.com', 'note' => null],
                'students' => [
                    ['name' => '山崎りこ', 'name_kana' => 'ヤマザキリコ', 'gender' => 'female', 'grade' => '小6', 'class' => 'B4', 'section' => ['B', '土'], 'note' => '人前で発表するのが得意'],
                ],
            ],
            [
                'guardian' => ['member_code' => '0017', 'name' => '岡田 修', 'name_kana' => 'オカダ オサム', 'email' => null, 'note' => null],
                'students' => [
                    ['name' => '岡田ことね', 'name_kana' => 'オカダコトネ', 'gender' => 'female', 'grade' => '小1', 'class' => 'A2', 'section' => ['A2', '土']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0018', 'name' => '前田 沙織', 'name_kana' => 'マエダ サオリ', 'email' => 'guardian0018@example.com', 'note' => null],
                'students' => [
                    ['name' => '前田ゆい', 'name_kana' => 'マエダユイ', 'gender' => 'female', 'grade' => '小1', 'class' => 'A2', 'section' => ['A2', '土'], 'note' => '体験時からタブレット操作にすぐ慣れていた'],
                ],
            ],
            [
                'guardian' => ['member_code' => '0019', 'name' => '藤田 亮', 'name_kana' => 'フジタ リョウ', 'email' => 'guardian0019@example.com', 'note' => null],
                'students' => [
                    ['name' => '藤田しおん', 'name_kana' => 'フジタシオン', 'gender' => null, 'grade' => '小1', 'class' => 'A2', 'section' => ['A2', '土'], 'note' => '家庭の都合により休会中（再開時期は未定）', 'leave' => 'current'],
                ],
            ],
            [
                'guardian' => ['member_code' => '0020', 'name' => '後藤 紀子', 'name_kana' => 'ゴトウ ノリコ', 'email' => 'guardian0020@example.com', 'note' => null],
                'students' => [
                    ['name' => '後藤みさき', 'name_kana' => 'ゴトウミサキ', 'gender' => 'female', 'grade' => '小2', 'class' => 'A3', 'section' => ['A3', '土']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0021', 'name' => '長谷川 聡', 'name_kana' => 'ハセガワ サトシ', 'email' => 'guardian0021@example.com', 'note' => null],
                'students' => [
                    ['name' => '長谷川ゆき', 'name_kana' => 'ハセガワユキ', 'gender' => 'female', 'grade' => '年中', 'class' => 'A1', 'section' => ['A1', '土']],
                ],
            ],
            [
                'guardian' => ['member_code' => '0022', 'name' => '村上 雅人', 'name_kana' => 'ムラカミ マサト', 'email' => 'guardian0022@example.com', 'note' => null],
                'students' => [
                    ['name' => '村上ひろと', 'name_kana' => 'ムラカミヒロト', 'gender' => 'male', 'grade' => '中1', 'class' => 'B5', 'section' => ['B', '土']],
                ],
            ],
        ];
    }
}
