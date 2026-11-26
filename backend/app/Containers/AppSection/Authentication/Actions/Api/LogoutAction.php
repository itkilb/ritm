<?php

namespace App\Containers\AppSection\Authentication\Actions\Api;

use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\Auth;

final class LogoutAction extends ParentAction
{
    public function run(): void
    {
        Auth::guard('web')->logout();

        $session = app('session.store');
        $session->invalidate();
        $session->regenerateToken();
    }
}
