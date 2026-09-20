<?php

namespace App\Concerns;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * guardian・staffはそれぞれ別テーブルを持ち、両者が同じメールアドレスを
     * 共有することも仕様上許容されているため、一意性は「ログイン中の
     * ガードと同じテーブル内」でのみチェックする（元は常にusersテーブルを
     * 見てしまっており、guardian・staffの重複を検知できていなかった）。
     *
     * whereNull('deleted_at')で論理削除済みの行を対象から除外する。
     * これがないと、退会（論理削除）済みのアカウントが使っていたメール
     * アドレスを、別の新しいアカウントが二度と使えなくなってしまう
     * （DB側のユニークインデックスも同様に対応済み。該当マイグレーション参照）。
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        // get_class()の静的な戻り値型にはPHPStanの都合上falseも含まれるが、
        // このtraitはGuardian・Staffの認証済みモデルに対してのみ使われるため、
        // 実際にfalseになることはない。Rule::unique()はstringを要求するため、
        // ここで型を明示しておく。
        /** @var class-string<Guardian>|class-string<Staff> $modelClass */
        $modelClass = get_class($this->user());

        $rule = Rule::unique($modelClass)->whereNull('deleted_at');

        if ($userId !== null) {
            $rule = $rule->ignore($userId);
        }

        return [
            'required',
            'string',
            'email',
            'max:255',
            $rule,
        ];
    }
}
