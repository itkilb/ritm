<?php

namespace App\Containers\AppSection\Exercise\Services;

use App\Containers\AppSection\Exercise\Data\ExerciseCatalog;

final class ExerciseModeCompatibility
{
    /**
     * @return array<int, string>
     */
    public static function modesFor(string $measurementType): array
    {
        return match ($measurementType) {
            ExerciseCatalog::TYPE_WEIGHTED => ['weighted', 'bodyweight'],
            ExerciseCatalog::TYPE_BODYWEIGHT => ['bodyweight', 'weighted'],
            ExerciseCatalog::TYPE_REPS => ['reps'],
            ExerciseCatalog::TYPE_TIME => ['time'],
            default => [],
        };
    }

    public static function compatible(string $measurementType, string $workoutMode): bool
    {
        return in_array($workoutMode, self::modesFor($measurementType), true);
    }

    public static function workoutModeFor(string $measurementType): string
    {
        return $measurementType;
    }
}
