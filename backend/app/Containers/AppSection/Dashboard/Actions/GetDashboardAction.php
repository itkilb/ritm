<?php

namespace App\Containers\AppSection\Dashboard\Actions;

use App\Containers\AppSection\ActivityEntry\Tasks\GetActivityEntriesForDateTask;
use App\Containers\AppSection\FoodEntry\Tasks\BuildFoodMealGroupsTask;
use App\Containers\AppSection\FoodEntry\Tasks\GetFoodEntriesForDateTask;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserGoal\Tasks\ResolveUserGoalForDateTask;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserTimezoneTask;
use App\Containers\AppSection\WaterEntry\Models\WaterEntry;
use App\Containers\AppSection\WaterEntry\Tasks\FindWaterEntryForDateTask;
use App\Containers\AppSection\Workout\Services\WorkoutTotals;
use App\Containers\AppSection\Workout\Tasks\GetWorkoutsForDateTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Collection;

final class GetDashboardAction extends ParentAction
{
    public function __construct(
        private readonly ResolveUserTimezoneTask $resolveUserTimezoneTask,
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
        private readonly ResolveUserGoalForDateTask $resolveUserGoalForDateTask,
        private readonly GetFoodEntriesForDateTask $getFoodEntriesForDateTask,
        private readonly BuildFoodMealGroupsTask $buildFoodMealGroupsTask,
        private readonly GetActivityEntriesForDateTask $getActivityEntriesForDateTask,
        private readonly FindWaterEntryForDateTask $findWaterEntryForDateTask,
        private readonly GetWorkoutsForDateTask $getWorkoutsForDateTask,
        private readonly WorkoutTotals $workoutTotals,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(User $user, string|null $requestedDate): array
    {
        $timezone = $this->resolveUserTimezoneTask->run($user);
        $today = $this->resolveUserLocalDateTask->run($user);
        $date = $requestedDate ?? $today;

        /** @var UserGoal|null $goal */
        $goal = $this->resolveUserGoalForDateTask->run($user, $date);
        /** @var Collection<int, \App\Containers\AppSection\FoodEntry\Models\FoodEntry> $foodEntries */
        $foodEntries = $this->getFoodEntriesForDateTask->run($user, $date);
        /** @var Collection<int, \App\Containers\AppSection\ActivityEntry\Models\ActivityEntry> $activityEntries */
        $activityEntries = $this->getActivityEntriesForDateTask->run($user, $date);
        /** @var Collection<int, \App\Containers\AppSection\Workout\Models\Workout> $workouts */
        $workouts = $this->getWorkoutsForDateTask->run($user, $date);
        $waterEntry = $this->findWaterEntryForDateTask->run($user, $date);

        $foodTotals = [
            'calories' => 0.0,
            'protein' => 0.0,
            'fat' => 0.0,
            'carbs' => 0.0,
        ];

        foreach ($foodEntries as $entry) {
            $foodTotals['calories'] += (float) $entry->calories;
            $foodTotals['protein'] += (float) $entry->protein;
            $foodTotals['fat'] += (float) $entry->fat;
            $foodTotals['carbs'] += (float) $entry->carbs;
        }

        foreach ($foodTotals as $key => $value) {
            $foodTotals[$key] = round($value, 2);
        }

        $mealGroups = $this->buildFoodMealGroupsTask->run($foodEntries);

        $activityTotals = [
            'calories' => 0.0,
            'steps' => 0,
            'minutes' => 0.0,
            'distance_km' => 0.0,
        ];

        foreach ($activityEntries as $entry) {
            $activityTotals['calories'] += (float) $entry->calories;
            $activityTotals['steps'] += (int) $entry->steps;
            $activityTotals['minutes'] += (float) $entry->minutes;
            $activityTotals['distance_km'] += (float) $entry->distance_km;
        }

        $activityTotals['calories'] = round($activityTotals['calories'], 2);
        $activityTotals['minutes'] = round($activityTotals['minutes'], 2);
        $activityTotals['distance_km'] = round($activityTotals['distance_km'], 3);

        $manualActivityTotals = $activityTotals;
        $workoutTotals = $this->workoutTotals->forWorkouts($workouts);

        // Workout calories are reported separately and never raise the food target.
        $activityTotals['calories'] = round($activityTotals['calories'] + $workoutTotals['calories'], 2);
        $activityTotals['minutes'] = round($activityTotals['minutes'] + $workoutTotals['minutes'], 2);
        $activityTotals['distance_km'] = round($activityTotals['distance_km'] + $workoutTotals['distance_km'], 3);

        $targetCalories = $goal?->calories;
        $eaten = $foodTotals['calories'];
        $remaining = null === $targetCalories ? null : max(0.0, (float) $targetCalories - $eaten);
        $excess = null === $targetCalories ? null : max(0.0, $eaten - (float) $targetCalories);

        $macros = [];
        foreach (['protein', 'fat', 'carbs'] as $macro) {
            $macros[$macro] = [
                'consumed' => $foodTotals[$macro],
                'target' => null === $goal ? null : (int) $goal->{$macro . '_grams'},
            ];
        }

        $stepsGoal = 10000;
        $stepsTotal = $activityTotals['steps'];

        return [
            'date' => $date,
            'timezone' => $timezone,
            'today' => $today,
            'goal' => $goal,
            'food' => [
                'entries' => $foodEntries,
                'totals' => $foodTotals,
                'has_entries' => $foodEntries->isNotEmpty(),
                'meal_groups' => $mealGroups['meal_groups'],
                'uncategorized' => $mealGroups['uncategorized'],
            ],
            'activity' => [
                'entries' => $activityEntries,
                'workouts' => $workouts,
                'totals' => $activityTotals,
                'manual_totals' => $manualActivityTotals,
                'workout_totals' => [
                    'calories' => $workoutTotals['calories'],
                    'minutes' => $workoutTotals['minutes'],
                    'distance_km' => $workoutTotals['distance_km'],
                    'count' => $workoutTotals['count'],
                    'uncalculated_count' => $workoutTotals['uncalculated_count'],
                ],
                'has_entries' => $activityEntries->isNotEmpty() || $workouts->isNotEmpty(),
            ],
            'water' => [
                'amount_ml' => (int) ($waterEntry?->amount_ml ?? 0),
                'target_ml' => WaterEntry::DAILY_TARGET_ML,
            ],
            'calories' => [
                'target' => null === $targetCalories ? null : (int) $targetCalories,
                'eaten' => $eaten,
                'remaining' => null === $remaining ? null : round($remaining, 2),
                'excess' => null === $excess ? null : round($excess, 2),
                'activity' => $activityTotals['calories'],
            ],
            'macros' => $macros,
            'steps' => [
                'total' => $stepsTotal,
                'goal' => $stepsGoal,
                'remaining' => max(0, $stepsGoal - $stepsTotal),
                'minutes' => $activityTotals['minutes'],
                'distance_km' => $activityTotals['distance_km'],
            ],
        ];
    }
}
