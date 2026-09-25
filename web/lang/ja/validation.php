<?php

return [
    'required' => ':attributeを入力してください。',
    'string' => ':attributeは文字列で入力してください。',
    'digits' => ':attributeは:digits桁の数字で入力してください。',
    'email' => ':attributeの形式が正しくありません。',
    'confirmed' => ':attributeと確認用の入力が一致しません。',
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'password' => [
        'letters' => ':attributeには英字を1文字以上含めてください。',
        'numbers' => ':attributeには数字を1文字以上含めてください。',
        'uncompromised' => 'この:attributeはよく使われていて安全性が低いため使用できません。別の:attributeを入力してください。',
    ],

    'attributes' => [
        'member_code' => '会員番号',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
    ],
];
