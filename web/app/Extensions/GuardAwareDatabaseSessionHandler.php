<?php

namespace App\Extensions;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;

class GuardAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * セッション保存のたびに、guardian・staffそれぞれのログイン状況を
     * sessionsテーブルの guardian_id / staff_id に独立して記録する。
     *
     * 1つのセッション（ブラウザ）でguardian・staff両方に同時ログインしている
     * ケースがあり得るため、片方だけを記録する設計（guard/auth_idの1組）では
     * もう片方が記録漏れになっていた。ガードごとに専用カラムを持たせることで
     * 両方を同時に、かつ独立して記録できるようにする。
     *
     * @return $this
     */
    protected function addUserInformation(&$payload)
    {
        parent::addUserInformation($payload);

        $payload['guardian_id'] = Auth::guard('guardian')->check()
            ? Auth::guard('guardian')->id()
            : null;

        $payload['staff_id'] = Auth::guard('staff')->check()
            ? Auth::guard('staff')->id()
            : null;

        return $this;
    }
}
