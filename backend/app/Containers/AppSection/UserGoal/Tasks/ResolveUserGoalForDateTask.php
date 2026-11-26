<?php

namespace App\Containers\AppSection\UserGoal\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ResolveUserGoalForDateTask extends ParentTask
{
    public function run(User $user, string $localDate): UserGoal|null
    {
        // Newest confirmation wins; effective_date alone is ambiguous across timezones.
        return UserGoal::query()
            ->where('user_id', $user->getKey())
            ->where('effective_date', '<=', $localDate)
            ->orderByDesc('id')
            ->first();
    }
}
