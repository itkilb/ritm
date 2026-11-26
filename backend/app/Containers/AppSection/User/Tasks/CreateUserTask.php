<?php

namespace App\Containers\AppSection\User\Tasks;

use App\Containers\AppSection\User\Data\Repositories\UserRepository;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CreateUserTask extends ParentTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function run(array $data): User
    {
        $data['email'] = strtolower(trim($data['email']));

        tap(validator(
            [
                'email' => $data['email'],
            ],
            [
                'email' => ['unique:users,email'],
            ],
        ))->validate();

        return $this->repository->create($data);
    }
}
