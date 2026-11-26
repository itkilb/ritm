<?php

namespace App\Containers\AppSection\Exercise\Tasks;

use App\Containers\AppSection\Exercise\Models\Exercise;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ResolveExerciseForUserTask extends ParentTask
{
    public function run(User $user, string|null $hashedId): Exercise|null
    {
        if (null === $hashedId || '' === $hashedId) {
            return null;
        }

        $id = $this->decodeId($hashedId);

        if (null === $id) {
            return null;
        }

        return Exercise::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
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
