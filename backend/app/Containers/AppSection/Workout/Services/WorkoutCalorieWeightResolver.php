<?php

namespace App\Containers\AppSection\Workout\Services;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Tasks\ResolveUserGoalForDateTask;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\WeightEntry\Tasks\GetLatestWeightEntryTask;
use App\Containers\AppSection\Workout\Models\Workout;

final class WorkoutCalorieWeightResolver
{
    public function __construct(
        private readonly ResolveUserGoalForDateTask $resolveUserGoalForDateTask,
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
        private readonly GetLatestWeightEntryTask $getLatestWeightEntryTask,
    ) {
    }

    /**
     * @return array{weight_kg: float|null, weight_source: string|null, warning: string|null}
     */
    // Priority: explicit override, saved snapshot, latest measurement, goal, profile fallback.
    public function resolve(
        User $user,
        string $date,
        float|null $explicitWeight,
        Workout|null $existing = null,
    ): array {
        if (null !== $explicitWeight) {
            return [
                'weight_kg' => $explicitWeight,
                'weight_source' => 'manual_override',
                'warning' => null,
            ];
        }

        if (
            null !== $existing
            && 'auto' === $existing->calories_mode
            && null !== $existing->weight_kg
        ) {
            return [
                'weight_kg' => (float) $existing->weight_kg,
                'weight_source' => $existing->weight_source ?? 'saved_snapshot',
                'warning' => null,
            ];
        }

        $measurement = $this->getLatestWeightEntryTask->run($user, $date);

        if (null !== $measurement) {
            return [
                'weight_kg' => (float) $measurement->weight_kg,
                'weight_source' => 'weight_entry',
                'warning' => null,
            ];
        }

        $goal = $this->resolveUserGoalForDateTask->run($user, $date);

        if (null !== $goal && null !== $goal->weight_kg) {
            return [
                'weight_kg' => (float) $goal->weight_kg,
                'weight_source' => 'goal_snapshot',
                'warning' => null,
            ];
        }

        $profile = UserProfile::query()->where('user_id', $user->getKey())->first();

        if (null === $profile) {
            return [
                'weight_kg' => null,
                'weight_source' => null,
                'warning' => null,
            ];
        }

        $today = $this->resolveUserLocalDateTask->run($user);
        $warning = $date < $today
            ? 'Исторической цели на эту дату нет — использован текущий вес профиля.'
            : null;

        return [
            'weight_kg' => (float) $profile->weight_kg,
            'weight_source' => 'current_profile_fallback',
            'warning' => $warning,
        ];
    }
}
