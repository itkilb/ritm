<?php

namespace App\Containers\AppSection\Authentication\Actions\Api;

use App\Containers\AppSection\Authentication\Tasks\AuthenticateUserTask;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginAction extends ParentAction
{
    public function __construct(
        private readonly AuthenticateUserTask $authenticateUserTask,
    ) {
    }

    public function run(string $email, string $password): User
    {
        $user = User::query()
            ->whereRaw('lower(email) = lower(?)', [trim($email)])
            ->first();

        if (!$user || !Hash::check($password, (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        $this->authenticateUserTask->run($user);

        return $user;
    }
}
