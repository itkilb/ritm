<?php

namespace App\Containers\AppSection\UserProfile\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindUserProfileByUserIdTask extends ParentTask
{
    public function run(int|string $userId): UserProfile|null
    {
        return UserProfile::query()->where('user_id', $userId)->first();
    }

    public function runForUser(User $user): UserProfile|null
    {
        return $this->run($user->getKey());
    }
}
