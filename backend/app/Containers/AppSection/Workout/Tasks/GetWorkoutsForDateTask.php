<?php

namespace App\Containers\AppSection\Workout\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetWorkoutsForDateTask extends ParentTask
{
    /**
     * @return Collection<int, Workout>
     */
    public function run(User $user, string $date): Collection
    {
        return Workout::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', $date)
            ->with(['exercises.exercise', 'exercises.sets'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
