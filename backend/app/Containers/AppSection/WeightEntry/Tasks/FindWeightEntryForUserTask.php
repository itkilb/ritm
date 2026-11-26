<?php

namespace App\Containers\AppSection\WeightEntry\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindWeightEntryForUserTask extends ParentTask
{
    public function run(User $user, string $hashedId, bool $lock = false): WeightEntry|null
    {
        $id = $this->decodeId($hashedId);

        if (null === $id) {
            return null;
        }

        $query = WeightEntry::query()
            ->where('user_id', $user->getKey())
            ->whereKey($id);

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
