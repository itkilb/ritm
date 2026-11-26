<?php

namespace App\Containers\AppSection\UserGoal\Data\DTOs;

final readonly class NutritionCalculation
{
    public function __construct(
        public int $age,
        public float $bmr,
        public float $tdee,
        public float $activityCoefficient,
        public float $goalMultiplier,
        public float|null $leanMassKg,
        public int $calories,
        public int $proteinGrams,
        public int $fatGrams,
        public int $carbsGrams,
        public bool $usedLeanMass,
    ) {
    }
}
