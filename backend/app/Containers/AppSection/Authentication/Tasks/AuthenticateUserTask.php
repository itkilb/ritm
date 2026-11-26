<?php

namespace App\Containers\AppSection\Authentication\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Auth;

final class AuthenticateUserTask extends ParentTask
{
    /**
     * Log the user into the server-side session. SessionGuard::login() already
     * migrates the session; regenerate once more so the CSRF token is rotated
     * as part of the explicit login contract.
     */
    public function run(User $user): void
    {
        Auth::guard('web')->login($user);
        app('session.store')->regenerate();
    }
}
