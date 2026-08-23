<?php

namespace Database\Factories;

use App\Models\Staff;
use Database\Seeders\Support\JapaneseNames;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected static ?string $password;

    private const NOTES = [
        '土曜日は固定シフトで毎週出勤',
        '出席管理のみ対応（フルアクセス権限なし）',
        '大学生アルバイト、平日夕方のみ勤務可',
        '前職はIT企業でプログラマーとして勤務',
        '来月から産休に入る予定',
        '保護者対応・電話対応が得意',
        '複数教室を掛け持ちで担当',
        '運営歴が長く、新人スタッフの教育も担当',
    ];

    // attendance_only（出席管理のみ）がfull_access（出席管理＋登録管理）より多くなるようにする想定の割合
    private const ATTENDANCE_ONLY_RATE = 80;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lastName = fake()->randomElement(JapaneseNames::STAFF_LAST_NAMES);
        $firstName = fake()->randomElement(JapaneseNames::STAFF_FIRST_NAMES);

        return [
            'member_code' => str_pad((string) fake()->unique()->numberBetween(5001, 9999), 4, '0', STR_PAD_LEFT),
            'name' => "{$lastName['kanji']} {$firstName['kanji']}",
            'name_kana' => "{$lastName['kana']} {$firstName['kana']}",
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->boolean(self::ATTENDANCE_ONLY_RATE) ? 'attendance_only' : 'full_access',
            'note' => fake()->optional(0.3)->randomElement(self::NOTES),
        ];
    }
}
