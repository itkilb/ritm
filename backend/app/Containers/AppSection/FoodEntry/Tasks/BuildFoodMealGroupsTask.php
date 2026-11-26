<?php

namespace App\Containers\AppSection\FoodEntry\Tasks;

use App\Containers\AppSection\FoodEntry\Enums\MealType;
use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class BuildFoodMealGroupsTask extends ParentTask
{
    /**
     * @param Collection<int, FoodEntry> $entries
     *
     * @return array{
     *     meal_groups: list<array{meal_type: string, entries: list<FoodEntry>, totals: array{calories: float, protein: float, fat: float, carbs: float}, count: int}>,
     *     uncategorized: array{meal_type: null, entries: list<FoodEntry>, totals: array{calories: float, protein: float, fat: float, carbs: float}, count: int}
     * }
     */
    public function run(Collection $entries): array
    {
        $mealGroups = [];

        foreach (MealType::cases() as $type) {
            $mealGroups[$type->value] = $this->emptyGroup($type->value);
        }

        $uncategorized = $this->emptyGroup(null);

        // Partition the loaded collection once; totals are rounded after summing.
        foreach ($entries as $entry) {
            $type = $entry->normalizedMealType();
            $key = $type?->value;

            if (null === $key) {
                $uncategorized['entries'][] = $entry;
                $this->accumulate($uncategorized['totals'], $entry);
                ++$uncategorized['count'];

                continue;
            }

            $mealGroups[$key]['entries'][] = $entry;
            $this->accumulate($mealGroups[$key]['totals'], $entry);
            ++$mealGroups[$key]['count'];
        }

        foreach ($mealGroups as $key => $group) {
            $mealGroups[$key]['totals'] = $this->roundTotals($group['totals']);
        }

        $uncategorized['totals'] = $this->roundTotals($uncategorized['totals']);

        return [
            'meal_groups' => array_values($mealGroups),
            'uncategorized' => $uncategorized,
        ];
    }

    /**
     * @return array{meal_type: string|null, entries: list<FoodEntry>, totals: array{calories: float, protein: float, fat: float, carbs: float}, count: int}
     */
    private function emptyGroup(string|null $mealType): array
    {
        return [
            'meal_type' => $mealType,
            'entries' => [],
            'totals' => [
                'calories' => 0.0,
                'protein' => 0.0,
                'fat' => 0.0,
                'carbs' => 0.0,
            ],
            'count' => 0,
        ];
    }

    /**
     * @param array{calories: float, protein: float, fat: float, carbs: float} $totals
     */
    private function accumulate(array &$totals, FoodEntry $entry): void
    {
        $totals['calories'] += (float) $entry->calories;
        $totals['protein'] += (float) $entry->protein;
        $totals['fat'] += (float) $entry->fat;
        $totals['carbs'] += (float) $entry->carbs;
    }

    /**
     * @param array{calories: float, protein: float, fat: float, carbs: float} $totals
     *
     * @return array{calories: float, protein: float, fat: float, carbs: float}
     */
    private function roundTotals(array $totals): array
    {
        return [
            'calories' => round($totals['calories'], 2),
            'protein' => round($totals['protein'], 2),
            'fat' => round($totals['fat'], 2),
            'carbs' => round($totals['carbs'], 2),
        ];
    }
}
