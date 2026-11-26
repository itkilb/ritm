<?php

namespace App\Containers\AppSection\WeightEntry\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class SyncProfileWeightEntryFromProfileTask extends ParentTask
{
    public function run(User $user, string $localDate, float $weightKg, bool $weightChanged): void
    {
        $existing = WeightEntry::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', $localDate)
            ->lockForUpdate()
            ->first();

        if (null === $existing) {
            WeightEntry::query()->create([
                'user_id' => $user->getKey(),
                'date' => $localDate,
                'weight_kg' => $weightKg,
            ]);

            return;
        }

        if (!$weightChanged) {
            return;
        }

        $existing->update(['weight_kg' => $weightKg]);
    }
}
