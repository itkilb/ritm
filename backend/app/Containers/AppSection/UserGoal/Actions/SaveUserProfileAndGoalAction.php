<?php

namespace App\Containers\AppSection\UserGoal\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserGoal\Services\NutritionGoalInputNormalizer;
use App\Containers\AppSection\UserGoal\Tasks\CreateUserGoalTask;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Containers\AppSection\UserProfile\Tasks\SaveUserProfileTask;
use App\Containers\AppSection\WeightEntry\Tasks\SyncProfileWeightEntryFromProfileTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SaveUserProfileAndGoalAction extends ParentAction
{
    public function __construct(
        private readonly CalculateNutritionGoalAction $calculateNutritionGoalAction,
        private readonly SaveUserProfileTask $saveUserProfileTask,
        private readonly CreateUserGoalTask $createUserGoalTask,
        private readonly SyncProfileWeightEntryFromProfileTask $syncWeightEntry,
    ) {
    }

    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage?: int|float|string|null, gender: string, activity_level: string, goal: string, timezone: string} $data
     *
     * @return array{user: User, profile: UserProfile, goal: UserGoal}
     */
    public function run(User $user, array $data): array
    {
        $data = NutritionGoalInputNormalizer::normalize($data);

        return DB::transaction(function () use ($user, $data): array {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $timezone = (string) $data['timezone'];
            $effectiveDate = CarbonImmutable::now($timezone)->format('Y-m-d');
            $calculation = $this->calculateNutritionGoalAction->run($data, $effectiveDate);

            // Capture the old weight; only a real change may overwrite today's measurement.
            $previousProfile = UserProfile::query()
                ->where('user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->first();
            $newWeight = (float) $data['weight_kg'];
            $weightChanged = null === $previousProfile
                || abs((float) $previousProfile->weight_kg - $newWeight) >= 0.000_000_1;

            $profile = $this->saveUserProfileTask->run($lockedUser, $data);
            $goal = $this->createUserGoalTask->run(
                $lockedUser,
                $data,
                $calculation,
                $effectiveDate,
                $timezone,
            );

            $this->syncWeightEntry->run($lockedUser, $effectiveDate, $newWeight, $weightChanged);

            $lockedUser->forceFill(['profile_completed' => true])->save();
            $lockedUser->setRelation('profile', $profile->fresh());

            return [
                'user' => $lockedUser,
                'profile' => $profile->fresh(),
                'goal' => $goal,
            ];
        }, 3);
    }
}
