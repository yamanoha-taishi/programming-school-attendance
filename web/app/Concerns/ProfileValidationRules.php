<?php

namespace App\Concerns;

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
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        $modelClass = get_class($this->user());

        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique($modelClass)
                : Rule::unique($modelClass)->ignore($userId),
        ];
    }
}
