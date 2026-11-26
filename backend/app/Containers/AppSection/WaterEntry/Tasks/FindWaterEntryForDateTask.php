<?php

namespace App\Containers\AppSection\WaterEntry\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WaterEntry\Models\WaterEntry;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindWaterEntryForDateTask extends ParentTask
{
    public function run(User $user, string $localDate): WaterEntry|null
    {
        return WaterEntry::query()
            ->where('user_id', $user->getKey())
            ->where('date', $localDate)
            ->first();
    }
}
