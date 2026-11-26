<?php

namespace App\Containers\AppSection\UserGoal\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindCurrentUserGoalTask extends ParentTask
{
    public function __construct(
        private readonly ResolveUserGoalForDateTask $resolveUserGoalForDateTask,
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
    ) {
    }

    public function run(User $user): UserGoal|null
    {
        return $this->resolveUserGoalForDateTask->run(
            $user,
            $this->resolveUserLocalDateTask->run($user),
        );
    }
}
