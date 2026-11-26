<?php

namespace App\Containers\AppSection\ActivityEntry\Tasks;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetActivityEntriesForDateTask extends ParentTask
{
    /**
     * @return Collection<int, ActivityEntry>
     */
    public function run(User $user, string $localDate): Collection
    {
        return ActivityEntry::query()
            ->where('user_id', $user->getKey())
            ->where('date', $localDate)
            ->orderBy('id')
            ->get();
    }
}
