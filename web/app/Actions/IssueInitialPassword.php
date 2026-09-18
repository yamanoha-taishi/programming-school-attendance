<?php

namespace App\Actions;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Support\Str;

class IssueInitialPassword
{
    public function handle(Guardian|Staff $user): string
    {
        $password = Str::password(8, symbols: false);

        // Guardian・Staffともに'password' => 'hashed'キャストを持つため、
        // 平文を代入するだけで保存時にハッシュ化される
        // （NewPasswordController・ProfileControllerと同じ方針で統一）。
        $user->password = $password;
        $user->save();

        return $password;
    }
}
