<?php

namespace App\Containers\AppSection\WeightEntry\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class UpdateTargetWeightAction extends ParentAction
{
    public function run(User $user, float|null $targetWeightKg): UserProfile
    {
        return DB::transaction(function () use ($user, $targetWeightKg): UserProfile {
            $profile = UserProfile::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (null === $profile) {
                abort(404, 'Сначала заполните анкету.');
            }

            $profile->update(['target_weight_kg' => $targetWeightKg]);

            return $profile->fresh();
        }, 3);
    }
}
