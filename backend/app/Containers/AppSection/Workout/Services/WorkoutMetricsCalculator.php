<?php

namespace App\Containers\AppSection\Workout\Services;

use App\Containers\AppSection\Workout\Enums\ExerciseMode;

// Distance in km (3 dp), speed 2 dp, pace whole seconds; invalid inputs return null.
final class WorkoutMetricsCalculator
{
    public function distanceFromSpeed(float $speedKmh, int $durationSeconds): float|null
    {
        if (!is_finite($speedKmh) || $speedKmh <= 0 || $durationSeconds <= 0) {
            return null;
        }

        return round($speedKmh * $durationSeconds / 3600, 3);
    }

    public function speedKmh(float|null $distanceKm, int $durationSeconds): float|null
    {
        if (null === $distanceKm || !is_finite($distanceKm) || $distanceKm <= 0 || $durationSeconds <= 0) {
            return null;
        }

        return round($distanceKm / ($durationSeconds / 3600), 2);
    }

    public function paceSecondsPerKm(float|null $distanceKm, int $durationSeconds): int|null
    {
        if (null === $distanceKm || !is_finite($distanceKm) || $distanceKm <= 0 || $durationSeconds <= 0) {
            return null;
        }

        return (int) round($durationSeconds / $distanceKm);
    }

    public function paceLabel(int|null $paceSecondsPerKm): string|null
    {
        if (null === $paceSecondsPerKm || $paceSecondsPerKm <= 0) {
            return null;
        }

        return sprintf(
            '%d:%02d',
            intdiv($paceSecondsPerKm, 60),
            $paceSecondsPerKm % 60,
        );
    }

    /**
     * @return array{speed_kmh: float|null, pace_seconds_per_km: int|null, pace: string|null}
     */
    public function cardio(float|null $distanceKm, int $durationSeconds): array
    {
        $paceSeconds = $this->paceSecondsPerKm($distanceKm, $durationSeconds);

        return [
            'speed_kmh' => $this->speedKmh($distanceKm, $durationSeconds),
            'pace_seconds_per_km' => $paceSeconds,
            'pace' => $this->paceLabel($paceSeconds),
        ];
    }

    /**
     * @param iterable<int, array{mode: string|ExerciseMode, sets: array<int, array{weight_kg?: mixed, reps?: mixed, seconds?: mixed}>}> $exercises
     */
    public function volumeKg(iterable $exercises): float
    {
        $volume = 0.0;

        foreach ($exercises as $exercise) {
            $mode = $exercise['mode'] instanceof ExerciseMode
                ? $exercise['mode']
                : ExerciseMode::from((string) $exercise['mode']);

            if (!$mode->usesWeight()) {
                continue;
            }

            foreach ($exercise['sets'] as $set) {
                $weight = (float) ($set['weight_kg'] ?? 0);
                $reps = (int) ($set['reps'] ?? 0);

                if (is_finite($weight) && $weight > 0 && $reps > 0) {
                    $volume += $weight * $reps;
                }
            }
        }

        return round($volume, 2);
    }
}
