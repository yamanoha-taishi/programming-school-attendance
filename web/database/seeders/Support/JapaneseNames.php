<?php

namespace Database\Seeders\Support;

class JapaneseNames
{
    // 苗字（guardians・studentsで共有し、同じ家族には同じ苗字を使う）
    public const FAMILY_LAST_NAMES = [
        ['kanji' => '佐藤', 'kana' => 'サトウ'],
        ['kanji' => '鈴木', 'kana' => 'スズキ'],
        ['kanji' => '高橋', 'kana' => 'タカハシ'],
        ['kanji' => '田中', 'kana' => 'タナカ'],
        ['kanji' => '伊藤', 'kana' => 'イトウ'],
        ['kanji' => '渡辺', 'kana' => 'ワタナベ'],
        ['kanji' => '山本', 'kana' => 'ヤマモト'],
        ['kanji' => '中村', 'kana' => 'ナカムラ'],
    ];

    // 保護者（大人）の名前
    public const GUARDIAN_FIRST_NAMES = [
        ['kanji' => '直子', 'kana' => 'ナオコ'],
        ['kanji' => '大輔', 'kana' => 'ダイスケ'],
        ['kanji' => '智子', 'kana' => 'トモコ'],
        ['kanji' => '健太', 'kana' => 'ケンタ'],
        ['kanji' => '由美', 'kana' => 'ユミ'],
        ['kanji' => '和也', 'kana' => 'カズヤ'],
        ['kanji' => '恵子', 'kana' => 'ケイコ'],
        ['kanji' => '拓也', 'kana' => 'タクヤ'],
    ];

    // 生徒（子供）の名前。ひらがな表記のみ
    public const CHILD_FIRST_NAMES = [
        ['hiragana' => 'ひな', 'kana' => 'ヒナ'],
        ['hiragana' => 'ゆい', 'kana' => 'ユイ'],
        ['hiragana' => 'ひろと', 'kana' => 'ヒロト'],
        ['hiragana' => 'れん', 'kana' => 'レン'],
        ['hiragana' => 'りこ', 'kana' => 'リコ'],
        ['hiragana' => 'みさき', 'kana' => 'ミサキ'],
    ];

    // スタッフの苗字
    public const STAFF_LAST_NAMES = [
        ['kanji' => '小林', 'kana' => 'コバヤシ'],
        ['kanji' => '加藤', 'kana' => 'カトウ'],
        ['kanji' => '吉田', 'kana' => 'ヨシダ'],
        ['kanji' => '山田', 'kana' => 'ヤマダ'],
        ['kanji' => '佐々木', 'kana' => 'ササキ'],
        ['kanji' => '松本', 'kana' => 'マツモト'],
        ['kanji' => '井上', 'kana' => 'イノウエ'],
        ['kanji' => '木村', 'kana' => 'キムラ'],
    ];

    // スタッフの名前
    public const STAFF_FIRST_NAMES = [
        ['kanji' => '真由美', 'kana' => 'マユミ'],
        ['kanji' => '隆', 'kana' => 'タカシ'],
        ['kanji' => '亜希子', 'kana' => 'アキコ'],
        ['kanji' => '秀樹', 'kana' => 'ヒデキ'],
        ['kanji' => '千夏', 'kana' => 'チナツ'],
        ['kanji' => '誠', 'kana' => 'マコト'],
        ['kanji' => '麻衣', 'kana' => 'マイ'],
        ['kanji' => '浩二', 'kana' => 'コウジ'],
    ];
}
