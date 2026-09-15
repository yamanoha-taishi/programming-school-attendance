<?php

namespace App\Actions;

use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IssueInitialPassword
{
    public function handle(Guardian|Staff $user): string
    {
        $password = Str::password(8, symbols: false);
        $user->password = Hash::make($password);
        $user->save();

        return $password;
    }
}
