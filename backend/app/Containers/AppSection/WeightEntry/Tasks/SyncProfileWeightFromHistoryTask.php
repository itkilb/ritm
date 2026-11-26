<?php

namespace App\Containers\AppSection\WeightEntry\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class SyncProfileWeightFromHistoryTask extends ParentTask
{
    public function run(User $user): void
    {
        // An empty journal keeps the existing profile weight as the fallback.
        $latest = WeightEntry::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (null === $latest) {
            return;
        }

        $profile = UserProfile::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if (null === $profile) {
            return;
        }

        $weight = (float) $latest->weight_kg;

        if (abs((float) $profile->weight_kg - $weight) < 0.000_000_1) {
            return;
        }

        $profile->update([
            'weight_kg' => $weight,
            'weight_kg_exact' => $weight,
        ]);
    }
}
