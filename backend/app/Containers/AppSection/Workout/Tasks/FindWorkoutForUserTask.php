<?php

namespace App\Containers\AppSection\Workout\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindWorkoutForUserTask extends ParentTask
{
    public function run(User $user, string $hashedId, bool $lock = false): Workout|null
    {
        $id = $this->decodeId($hashedId);

        if (null === $id) {
            return null;
        }

        $query = Workout::query()
            ->where('user_id', $user->getKey())
            ->whereKey($id)
            ->with(['exercises.exercise', 'exercises.sets']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function decodeId(string $hashedId): int|null
    {
        try {
            $decoded = hashids()->decode($hashedId);
        } catch (\Throwable) {
            return null;
        }

        return is_int($decoded) ? $decoded : null;
    }
}
