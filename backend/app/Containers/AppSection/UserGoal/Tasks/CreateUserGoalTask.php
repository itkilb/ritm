<?php

namespace App\Containers\AppSection\UserGoal\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Data\DTOs\NutritionCalculation;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserGoal\Services\NutritionGoalInputNormalizer;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CreateUserGoalTask extends ParentTask
{
    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage?: int|float|string|null, gender: string, activity_level: string, goal: string} $input
     */
    public function run(
        User $user,
        array $input,
        NutritionCalculation $calculation,
        string $effectiveDate,
        string $timezone,
    ): UserGoal {
        $input = NutritionGoalInputNormalizer::normalize($input);

        $weightKg = (float) $input['weight_kg'];
        $heightCm = (float) $input['height_cm'];
        $fatPercentage = is_null($input['fat_percentage']) ? null : (float) $input['fat_percentage'];

        return UserGoal::query()->create([
            'user_id' => $user->getKey(),
            'effective_date' => $effectiveDate,
            'timezone' => $timezone,
            'weight_kg' => $weightKg,
            'weight_kg_exact' => $weightKg,
            'height_cm' => $heightCm,
            'height_cm_exact' => $heightCm,
            'birth_date' => $input['birth_date'],
            'fat_percentage' => $fatPercentage,
            'fat_percentage_exact' => $fatPercentage,
            'gender' => (string) $input['gender'],
            'activity_level' => (string) $input['activity_level'],
            'goal' => (string) $input['goal'],
            'age' => $calculation->age,
            'bmr' => $calculation->bmr,
            'bmr_exact' => $calculation->bmr,
            'tdee' => $calculation->tdee,
            'tdee_exact' => $calculation->tdee,
            'activity_coefficient' => $calculation->activityCoefficient,
            'goal_multiplier' => $calculation->goalMultiplier,
            'lean_mass_kg' => $calculation->leanMassKg,
            'lean_mass_kg_exact' => $calculation->leanMassKg,
            'calories' => $calculation->calories,
            'protein_grams' => $calculation->proteinGrams,
            'fat_grams' => $calculation->fatGrams,
            'carbs_grams' => $calculation->carbsGrams,
        ]);
    }
}
