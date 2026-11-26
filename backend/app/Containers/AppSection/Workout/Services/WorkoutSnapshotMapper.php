<?php

namespace App\Containers\AppSection\Workout\Services;

use App\Containers\AppSection\Workout\Data\CalorieCalculation;

final class WorkoutSnapshotMapper
{
    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    public function columns(array $normalized, CalorieCalculation $calculation): array
    {
        return array_merge(
            [
                'date' => $normalized['date'],
                'type' => $normalized['type'],
                'name' => $normalized['name'],
                'duration_seconds' => $normalized['duration_seconds'],
                'feeling' => $normalized['feeling'],
                'note' => $normalized['note'],
                'distance_km' => $normalized['distance_km'],
                'pulse' => $normalized['pulse'],
                'incline_percent' => $normalized['incline_percent'],
                'run_surface' => $normalized['run_surface'],
                'activity_kind' => $normalized['activity_kind'],
                'strength_character' => $normalized['strength_character'],
                'cardio_intensity' => $normalized['cardio_intensity'],
                'power_watts' => $normalized['power_watts'],
            ],
            $calculation->toColumns(),
        );
    }
}
