<?php

namespace App\Containers\AppSection\Workout\Services;

use App\Containers\AppSection\Workout\Enums\WorkoutType;
use App\Containers\AppSection\Workout\Models\Workout;

// distance_km is run-only; manual activity and workouts remain separate sources.
final class WorkoutTotals
{
    /**
     * @param iterable<int, Workout> $workouts
     *
     * @return array{count: int, duration_seconds: int, minutes: float, calories: int, distance_km: float, uncalculated_count: int}
     */
    public function forWorkouts(iterable $workouts): array
    {
        $count = 0;
        $durationSeconds = 0;
        $calories = 0;
        $runDistance = 0.0;
        $uncalculatedCount = 0;

        foreach ($workouts as $workout) {
            ++$count;
            $durationSeconds += (int) $workout->duration_seconds;
            if (null === $workout->calories) {
                ++$uncalculatedCount;
            } else {
                $calories += (int) $workout->calories;
            }

            if (WorkoutType::RUN === $workout->type && null !== $workout->distance_km) {
                $runDistance += (float) $workout->distance_km;
            }
        }

        return [
            'count' => $count,
            'duration_seconds' => $durationSeconds,
            'minutes' => round($durationSeconds / 60, 2),
            'calories' => $calories,
            'distance_km' => round($runDistance, 3),
            'uncalculated_count' => $uncalculatedCount,
        ];
    }
}
