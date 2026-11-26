<?php

namespace App\Containers\AppSection\UserGoal\Actions;

use App\Containers\AppSection\UserGoal\Data\DTOs\NutritionCalculation;
use App\Containers\AppSection\UserGoal\Services\NutritionGoalInputNormalizer;
use App\Ship\Parents\Actions\Action as ParentAction;
use Carbon\CarbonImmutable;

final class PreviewNutritionGoalAction extends ParentAction
{
    public function __construct(
        private readonly CalculateNutritionGoalAction $calculateNutritionGoalAction,
    ) {
    }

    /**
     * @param array{weight_kg: int|float|string, height_cm: int|float|string, birth_date: string, fat_percentage?: int|float|string|null, gender: string, activity_level: string, goal: string, timezone: string} $data
     *
     * @return array{local_date: string, calculation: NutritionCalculation}
     */
    public function run(array $data): array
    {
        $data = NutritionGoalInputNormalizer::normalize($data);
        $localDate = CarbonImmutable::now((string) $data['timezone'])->format('Y-m-d');

        return [
            'local_date' => $localDate,
            'calculation' => $this->calculateNutritionGoalAction->run($data, $localDate),
        ];
    }
}
