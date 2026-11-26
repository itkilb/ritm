<?php

namespace App\Containers\AppSection\Workout\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserTimezoneTask;
use App\Containers\AppSection\Workout\Services\WorkoutTotals;
use App\Containers\AppSection\Workout\Tasks\GetRecentWorkoutsTask;
use App\Containers\AppSection\Workout\Tasks\GetWorkoutsForDateRangeTask;
use App\Containers\AppSection\Workout\Tasks\GetWorkoutsForDateTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Carbon\CarbonImmutable;

final class GetWorkoutDiaryAction extends ParentAction
{
    private const DAYS_IN_WEEK = 7;

    public function __construct(
        private readonly ResolveUserTimezoneTask $resolveUserTimezoneTask,
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
        private readonly GetWorkoutsForDateTask $getWorkoutsForDateTask,
        private readonly GetWorkoutsForDateRangeTask $getWorkoutsForDateRangeTask,
        private readonly GetRecentWorkoutsTask $getRecentWorkoutsTask,
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
        $weekStart = $this->mondayOf($date);
        $weekEnd = $this->addDays($weekStart, self::DAYS_IN_WEEK - 1);

        $dayWorkouts = $this->getWorkoutsForDateTask->run($user, $date);
        $weekWorkouts = $this->getWorkoutsForDateRangeTask->run($user, $weekStart, $weekEnd);
        $recentWorkouts = $this->getRecentWorkoutsTask->run($user);

        $byDate = [];

        foreach ($weekWorkouts as $workout) {
            $byDate[$workout->date->format('Y-m-d')][] = $workout;
        }

        $days = [];

        for ($offset = 0; $offset < self::DAYS_IN_WEEK; ++$offset) {
            $dayDate = $this->addDays($weekStart, $offset);
            $totals = $this->workoutTotals->forWorkouts($byDate[$dayDate] ?? []);

            $days[] = [
                'date' => $dayDate,
                'count' => $totals['count'],
                'minutes' => $totals['minutes'],
                'distance_km' => $totals['distance_km'],
                'has_workouts' => $totals['count'] > 0,
            ];
        }

        return [
            'date' => $date,
            'timezone' => $timezone,
            'today' => $today,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'workouts' => $dayWorkouts,
            'days' => $days,
            'week' => $this->workoutTotals->forWorkouts($weekWorkouts),
            'recent' => $recentWorkouts,
        ];
    }

    private function mondayOf(string $date): string
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
        $isoWeekday = (int) $parsed->format('N');

        return $parsed->subDays($isoWeekday - 1)->format('Y-m-d');
    }

    private function addDays(string $date, int $days): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC')
            ->addDays($days)
            ->format('Y-m-d');
    }
}
