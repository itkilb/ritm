<?php

namespace App\Containers\AppSection\Workout\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetRecentWorkoutsTask extends ParentTask
{
    /**
     * @return Collection<int, Workout>
     */
    public function run(User $user, int $limit = 4): Collection
    {
        return Workout::query()
            ->where('user_id', $user->getKey())
            ->with(['exercises.exercise', 'exercises.sets'])
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
