<?php

namespace App\Extensions;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;

class GuardAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * セッション保存のたびに、guardian・staffどちらのガードで
     * ログイン中かをsessionsテーブルの guard / auth_id に記録する。
     *
     * @return $this
     */
    protected function addUserInformation(&$payload)
    {
        parent::addUserInformation($payload);

        foreach (['guardian', 'staff'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $payload['auth_id'] = Auth::guard($guard)->id();
                $payload['guard'] = $guard;

                return $this;
            }
        }

        $payload['auth_id'] = null;
        $payload['guard'] = null;

        return $this;
    }
}
