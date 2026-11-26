<?php

namespace App\Containers\AppSection\Authentication\Actions\Api;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;
use App\Containers\AppSection\User\Tasks\CreateUserTask;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterAction extends ParentAction
{
    public function __construct(
        private readonly CreateUserTask $createUserTask,
    ) {
    }

    public function run(array $data): User
    {
        try {
            $user = DB::transaction(fn (): User => $this->createUserTask->run([
                'email' => $data['email'],
                'password' => $data['password'],
            ]));
        } catch (QueryException $exception) {
            if ($this->isDuplicateEmail($exception)) {
                throw ValidationException::withMessages([
                    'email' => ['Этот email уже зарегистрирован.'],
                ]);
            }

            throw $exception;
        }

        event(new Registered($user));

        return $user;
    }

    private function isDuplicateEmail(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && str_contains(strtolower($exception->getMessage()), 'users_email_unique');
    }
}
