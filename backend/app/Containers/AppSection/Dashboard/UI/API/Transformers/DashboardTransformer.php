<?php

namespace App\Containers\AppSection\Dashboard\UI\API\Transformers;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\ActivityEntry\UI\API\Transformers\ActivityEntryTransformer;
use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\FoodEntry\UI\API\Transformers\FoodEntryTransformer;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\UI\API\Transformers\WorkoutTransformer;

final class DashboardTransformer
{
    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    public function transform(array $summary): array
    {
        /** @var UserGoal|null $goal */
        $goal = $summary['goal'];

        return [
            'date' => $summary['date'],
            'timezone' => $summary['timezone'],
            'today' => $summary['today'],
            'target' => $goal ? [
                'id' => $goal->getHashedKey(),
                'effective_date' => $goal->effective_date->format('Y-m-d'),
                'calories' => $goal->calories,
                'protein' => $goal->protein_grams,
                'fat' => $goal->fat_grams,
                'carbs' => $goal->carbs_grams,
                'activity_level' => $goal->activity_level->value,
                'goal' => $goal->goal->value,
            ] : null,
            'food' => [
                'entries' => array_values(array_map(
                    static fn (FoodEntry $entry): array => (new FoodEntryTransformer())->transform($entry),
                    $summary['food']['entries']->all(),
                )),
                'totals' => $summary['food']['totals'],
                'has_entries' => $summary['food']['has_entries'],
                'meal_groups' => array_values(array_map(
                    fn (array $group): array => $this->transformMealGroup($group),
                    $summary['food']['meal_groups'],
                )),
                'uncategorized' => $this->transformMealGroup($summary['food']['uncategorized']),
            ],
            'activity' => [
                'entries' => array_values(array_map(
                    static fn (ActivityEntry $entry): array => (new ActivityEntryTransformer())->transform($entry),
                    $summary['activity']['entries']->all(),
                )),
                'workouts' => array_values(array_map(
                    static fn (Workout $workout): array => (new WorkoutTransformer())->transform($workout),
                    $summary['activity']['workouts']->all(),
                )),
                'totals' => $summary['activity']['totals'],
                'manual_totals' => $summary['activity']['manual_totals'],
                'workout_totals' => $summary['activity']['workout_totals'],
                'has_entries' => $summary['activity']['has_entries'],
            ],
            'water' => $summary['water'],
            'calories' => $summary['calories'],
            'macros' => $summary['macros'],
            'steps' => $summary['steps'],
        ];
    }

    /**
     * @param array<string, mixed> $group
     *
     * @return array<string, mixed>
     */
    private function transformMealGroup(array $group): array
    {
        return [
            'meal_type' => $group['meal_type'],
            'entries' => array_values(array_map(
                static fn (FoodEntry $entry): array => (new FoodEntryTransformer())->transform($entry),
                $group['entries'],
            )),
            'totals' => $group['totals'],
            'count' => $group['count'],
        ];
    }
}
