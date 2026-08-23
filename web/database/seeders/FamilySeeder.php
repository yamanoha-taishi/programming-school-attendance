<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Database\Seeders\Support\JapaneseNames;
use Illuminate\Database\Seeder;

class FamilySeeder extends Seeder
{
    private const FAMILY_COUNT = 20;

    private const SECTION_CAPACITY = 6;

    private const GRADES = [
        '年中', '年長', '小1', '小2', '小3', '小4', '小5', '小6', '中1', '中2', '中3',
    ];

    private const A_CLASS_CODE_BY_GRADE = [
        '年中' => 'A1',
        '年長' => 'A1',
        '小1' => 'A2',
        '小2' => 'A3',
    ];

    private const B_CLASS_CODES = ['B1', 'B2', 'B3', 'B4', 'B5', 'B6'];

    /** @var array<string, int[]> 授業実施単位名（A1／A2／A3／B）ごとのsection_id一覧 */
    private array $sectionIdsByName = [];

    /** @var array<int, int> section_id ごとの残席数（初期値6） */
    private array $remainingSeats = [];

    /** @var array<string, int> school_classes.code => id */
    private array $schoolClassIdByCode = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->loadSeats();
        $this->schoolClassIdByCode = SchoolClass::pluck('id', 'code')->all();

        for ($i = 1; $i <= self::FAMILY_COUNT; $i++) {
            $lastName = fake()->randomElement(JapaneseNames::FAMILY_LAST_NAMES);
            $guardianFirstName = fake()->randomElement(JapaneseNames::GUARDIAN_FIRST_NAMES);

            $guardian = Guardian::factory()->create([
                'member_code' => str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => "{$lastName['kanji']} {$guardianFirstName['kanji']}",
                'name_kana' => "{$lastName['kana']} {$guardianFirstName['kana']}",
            ]);

            $childCount = fake()->numberBetween(1, 2);

            for ($c = 0; $c < $childCount; $c++) {
                $placement = $this->pickPlacement();

                if ($placement === null) {
                    // どの授業実施単位も定員（6名）に達したため、これ以上は生徒を作らない
                    $this->command?->warn('全ての授業実施単位が定員に達したため、FamilySeederを打ち切ります。');

                    return;
                }

                [$grade, $schoolClassCode, $sectionId] = $placement;

                $childFirstName = fake()->randomElement(JapaneseNames::CHILD_FIRST_NAMES);

                $factory = Student::factory();

                if (fake()->boolean(10)) {
                    $factory = $factory->onLeave();
                }

                $factory->create([
                    'guardian_id' => $guardian->id,
                    'school_class_id' => $this->schoolClassIdByCode[$schoolClassCode],
                    'section_id' => $sectionId,
                    'grade' => $grade,
                    'name' => "{$lastName['kanji']}{$childFirstName['hiragana']}",
                    'name_kana' => "{$lastName['kana']}{$childFirstName['kana']}",
                ]);

                $this->remainingSeats[$sectionId]--;
            }
        }
    }

    /**
     * A1／A2／A3／B（B1〜B6共通）ごとに、該当するsection_idと残席数（6席）を初期化する。
     */
    private function loadSeats(): void
    {
        foreach (['A1', 'A2', 'A3', 'B'] as $name) {
            $sectionIds = Section::where('name', $name)->pluck('id')->all();
            $this->sectionIdsByName[$name] = $sectionIds;

            foreach ($sectionIds as $sectionId) {
                $this->remainingSeats[$sectionId] = self::SECTION_CAPACITY;
            }
        }
    }

    /**
     * 学年から授業クラスを決め、そのクラスが使う授業実施単位（section）の中から
     * 空き（残席1以上）を1つ選ぶ。空きが見つからない場合は学年を変えて再抽選する。
     *
     * @return array{0: string, 1: string, 2: int}|null [学年, 授業クラスコード, section_id]
     */
    private function pickPlacement(): ?array
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $grade = fake()->randomElement(self::GRADES);
            $schoolClassCode = self::A_CLASS_CODE_BY_GRADE[$grade] ?? fake()->randomElement(self::B_CLASS_CODES);
            $sectionName = str_starts_with($schoolClassCode, 'B') ? 'B' : $schoolClassCode;

            $availableSectionIds = array_values(array_filter(
                $this->sectionIdsByName[$sectionName],
                fn (int $sectionId) => $this->remainingSeats[$sectionId] > 0,
            ));

            if ($availableSectionIds !== []) {
                $sectionId = fake()->randomElement($availableSectionIds);

                return [$grade, $schoolClassCode, $sectionId];
            }
        }

        return null;
    }
}
