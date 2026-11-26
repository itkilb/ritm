<?php

namespace App\Containers\AppSection\Workout\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetWorkoutsForDateRangeTask extends ParentTask
{
    /**
     * @return Collection<int, Workout>
     */
    public function run(User $user, string $start, string $end): Collection
    {
        return Workout::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->with(['exercises.exercise', 'exercises.sets'])
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
