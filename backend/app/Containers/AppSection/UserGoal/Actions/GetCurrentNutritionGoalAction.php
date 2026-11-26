<?php

namespace App\Containers\AppSection\UserGoal\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserGoal\Tasks\FindCurrentUserGoalTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetCurrentNutritionGoalAction extends ParentAction
{
    public function __construct(
        private readonly FindCurrentUserGoalTask $findCurrentUserGoalTask,
    ) {
    }

    public function run(User $user): UserGoal|null
    {
        return $this->findCurrentUserGoalTask->run($user);
    }
}
