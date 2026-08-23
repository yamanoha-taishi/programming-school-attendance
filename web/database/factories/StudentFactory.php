<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Database\Seeders\Support\JapaneseNames;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
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

    private const NOTES = [
        '人見知りのため、最初は保護者同伴で参加',
        '軽度のアレルギーあり（詳細は保護者へ確認）',
        'タイピングがまだ苦手なので個別サポートが必要',
        '兄弟でクラスが違うため、お迎え時間がずれることがある',
        '落ち着いて着席するのが少し苦手',
        'プログラミング経験があり、進度が早め',
        '人前で発表するのが得意',
        '体験時からタブレット操作にすぐ慣れていた',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->randomElement(self::GRADES);
        $schoolClassCode = self::A_CLASS_CODE_BY_GRADE[$grade] ?? fake()->randomElement(self::B_CLASS_CODES);
        $sectionName = str_starts_with($schoolClassCode, 'B') ? 'B' : $schoolClassCode;
        $section = Section::where('name', $sectionName)->inRandomOrder()->firstOrFail();

        $lastName = fake()->randomElement(JapaneseNames::FAMILY_LAST_NAMES);
        $firstName = fake()->randomElement(JapaneseNames::CHILD_FIRST_NAMES);

        return [
            'guardian_id' => Guardian::inRandomOrder()->firstOrFail()->id,
            'school_class_id' => SchoolClass::where('code', $schoolClassCode)->firstOrFail()->id,
            'section_id' => $section->id,
            'grade' => $grade,
            'gender' => fake()->optional(0.9)->randomElement(['male', 'female']),
            'name' => "{$lastName['kanji']}{$firstName['hiragana']}",
            'name_kana' => "{$lastName['kana']}{$firstName['kana']}",
            'note' => fake()->optional(0.3)->randomElement(self::NOTES),
            'leave_from' => null,
            'leave_until' => null,
        ];
    }

    /**
     * 休会中の状態。
     */
    public function onLeave(): static
    {
        return $this->state(function () {
            $leaveFrom = fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-01');

            return [
                'leave_from' => $leaveFrom,
                'leave_until' => fake()->optional(0.5)->dateTimeBetween($leaveFrom, '+3 months')?->format('Y-m-t'),
            ];
        });
    }
}
