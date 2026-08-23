<?php

namespace Database\Factories;

use App\Models\Guardian;
use Database\Seeders\Support\JapaneseNames;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected static ?string $password;

    private const NOTES = [
        'お迎えは祖母が担当することがあります',
        '緊急時は携帯電話へご連絡ください',
        '月謝は口座振替を希望',
        '下のお子さまも来年度入会予定',
        '平日日中は仕事のため電話に出られないことが多いです',
        'きょうだいで通室中',
        '引っ越し予定のため、今後住所変更の可能性あり',
        '連絡は電話よりメール希望',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lastName = fake()->randomElement(JapaneseNames::FAMILY_LAST_NAMES);
        $firstName = fake()->randomElement(JapaneseNames::GUARDIAN_FIRST_NAMES);

        return [
            'member_code' => str_pad((string) fake()->unique()->numberBetween(1, 4999), 4, '0', STR_PAD_LEFT),
            'name' => "{$lastName['kanji']} {$firstName['kanji']}",
            'name_kana' => "{$lastName['kana']} {$firstName['kana']}",
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'note' => fake()->optional(0.3)->randomElement(self::NOTES),
        ];
    }
}
