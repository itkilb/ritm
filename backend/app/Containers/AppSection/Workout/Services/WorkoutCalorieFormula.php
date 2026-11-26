<?php

namespace App\Containers\AppSection\Workout\Services;

/**
 * active_kcal = round((MET - 1) × weight × seconds / 3600);
 * gross_kcal = round(MET × weight × seconds / 3600).
 */
final class WorkoutCalorieFormula
{
    public function active(?float $met, ?float $weightKg, ?int $durationSeconds): int|null
    {
        if (null === $met || null === $weightKg || null === $durationSeconds) {
            return null;
        }

        if (!is_finite($met) || !is_finite($weightKg)) {
            return null;
        }

        if ($met < 1.0 || $weightKg <= 0.0 || $durationSeconds <= 0) {
            return null;
        }

        return (int) round(($met - 1.0) * $weightKg * $durationSeconds / 3600);
    }

    public function gross(?float $met, ?float $weightKg, ?int $durationSeconds): int|null
    {
        if (null === $met || null === $weightKg || null === $durationSeconds) {
            return null;
        }

        if (!is_finite($met) || !is_finite($weightKg)) {
            return null;
        }

        if ($met <= 0.0 || $weightKg <= 0.0 || $durationSeconds <= 0) {
            return null;
        }

        return (int) round($met * $weightKg * $durationSeconds / 3600);
    }
}
