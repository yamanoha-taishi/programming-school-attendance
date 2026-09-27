<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    // デモ用スタッフ（固定データ）。本番（デモ環境）でも同じデータになるよう、Faker・Factoryは使わない。
    // 5001（full_access）と5002（attendance_only）を、デモ用ログインアカウントとして案内する想定。
    // 5005はメール未登録（パスワード再設定の申請画面で問い合わせ案内が出るケース）。
    private const STAFF = [
        [
            'member_code' => '5001',
            'name' => '小林 真由美',
            'name_kana' => 'コバヤシ マユミ',
            'email' => 'staff5001@example.com',
            'role' => 'full_access',
            'note' => '運営歴が長く、新人スタッフの教育も担当',
        ],
        [
            'member_code' => '5002',
            'name' => '加藤 隆',
            'name_kana' => 'カトウ タカシ',
            'email' => 'staff5002@example.com',
            'role' => 'attendance_only',
            'note' => '大学生アルバイト、平日夕方のみ勤務可',
        ],
        [
            'member_code' => '5003',
            'name' => '吉田 亜希子',
            'name_kana' => 'ヨシダ アキコ',
            'email' => 'staff5003@example.com',
            'role' => 'attendance_only',
            'note' => '土曜日は固定シフトで毎週出勤',
        ],
        [
            'member_code' => '5004',
            'name' => '山田 秀樹',
            'name_kana' => 'ヤマダ ヒデキ',
            'email' => 'staff5004@example.com',
            'role' => 'full_access',
            'note' => null,
        ],
        [
            'member_code' => '5005',
            'name' => '佐々木 千夏',
            'name_kana' => 'ササキ チナツ',
            'email' => null,
            'role' => 'attendance_only',
            'note' => null,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        foreach (self::STAFF as $staff) {
            Staff::create([...$staff, 'password' => $password]);
        }
    }
}
