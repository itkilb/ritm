<?php

namespace App\Containers\AppSection\UserProfile\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Carbon\CarbonImmutable;

final class ResolveUserLocalDateTask extends ParentTask
{
    public function __construct(
        private readonly ResolveUserTimezoneTask $resolveUserTimezoneTask,
    ) {
    }

    public function run(User $user): string
    {
        return CarbonImmutable::now($this->resolveUserTimezoneTask->run($user))->format('Y-m-d');
    }
}
