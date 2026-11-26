<?php

namespace App\Containers\AppSection\UserProfile\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Services\NutritionGoalInputNormalizer;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class SaveUserProfileTask extends ParentTask
{
    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage?: int|float|string|null, gender: string, activity_level: string, goal: string, timezone: string} $data
     */
    public function run(User $user, array $data): UserProfile
    {
        $data = NutritionGoalInputNormalizer::normalize($data);

        $weightKg = (float) $data['weight_kg'];
        $heightCm = (float) $data['height_cm'];
        $fatPercentage = is_null($data['fat_percentage']) ? null : (float) $data['fat_percentage'];

        return UserProfile::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'weight_kg' => $weightKg,
                'weight_kg_exact' => $weightKg,
                'height_cm' => $heightCm,
                'height_cm_exact' => $heightCm,
                'birth_date' => $data['birth_date'],
                'fat_percentage' => $fatPercentage,
                'fat_percentage_exact' => $fatPercentage,
                'gender' => (string) $data['gender'],
                'activity_level' => (string) $data['activity_level'],
                'goal' => (string) $data['goal'],
                'timezone' => (string) $data['timezone'],
            ],
        );
    }
}
