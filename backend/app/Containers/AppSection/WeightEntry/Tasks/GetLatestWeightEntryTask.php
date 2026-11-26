<?php

namespace App\Containers\AppSection\WeightEntry\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetLatestWeightEntryTask extends ParentTask
{
    public function run(User $user, string|null $onOrBefore = null): WeightEntry|null
    {
        $query = WeightEntry::query()->where('user_id', $user->getKey());

        if (null !== $onOrBefore) {
            $query->whereDate('date', '<=', $onOrBefore);
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }
}
