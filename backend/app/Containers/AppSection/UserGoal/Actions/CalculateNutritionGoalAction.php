<?php

namespace App\Containers\AppSection\UserGoal\Actions;

use App\Containers\AppSection\UserGoal\Data\DTOs\NutritionCalculation;
use App\Containers\AppSection\UserGoal\Services\NutritionCalculator;
use App\Containers\AppSection\UserGoal\Services\NutritionGoalInputNormalizer;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CalculateNutritionGoalAction extends ParentAction
{
    public function __construct(
        private readonly NutritionCalculator $nutritionCalculator,
    ) {
    }

    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage?: int|float|string|null, gender: string, activity_level: string, goal: string} $input
     */
    public function run(array $input, string $localDate): NutritionCalculation
    {
        return $this->nutritionCalculator->calculate(
            NutritionGoalInputNormalizer::normalize($input),
            $localDate,
        );
    }
}
