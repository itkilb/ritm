<?php

namespace App\Containers\AppSection\Workout\Services;

use App\Containers\AppSection\Workout\Enums\WorkoutType;

final class WorkoutPayloadNormalizer
{
    public function __construct(
        private readonly WorkoutMetricsCalculator $metrics,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     date: string,
     *     type: string,
     *     name: string,
     *     duration_seconds: int,
     *     feeling: string|null,
     *     note: string|null,
     *     distance_km: float|null,
     *     pulse: int|null,
     *     incline_percent: float|null,
     *     exercises: array<int, array{exercise_id: string|null, name: string, mode: string, position: int, sets: array<int, array{position: int, weight_kg: float|null, reps: int|null, seconds: int|null}>}>,
     *     calories: int|null,
     *     calories_mode: string,
     *     legacy_calories: bool,
     *     calorie_weight_kg: float|null,
     *     strength_character: string|null,
     *     activity_kind: string|null,
     *     cardio_intensity: string|null,
     *     power_watts: int|null,
     *     incline_ignored: bool,
     *     calories_recalculate: bool
     * }
     */
    public function normalize(array $data): array
    {
        $type = WorkoutType::from((string) $data['type']);
        $durationSeconds = (int) $data['duration_seconds'];
        $note = isset($data['note']) && is_string($data['note']) ? trim($data['note']) : '';

        // Legacy clients without calories_mode keep manual/default-0 semantics.
        $modeProvided = array_key_exists('calories_mode', $data) && null !== $data['calories_mode'];
        $mode = $modeProvided ? (string) $data['calories_mode'] : WorkoutCalorieService::MODE_MANUAL;
        $legacy = !$modeProvided;

        if (WorkoutCalorieService::MODE_AUTO === $mode) {
            $calories = null;
        } elseif ($legacy) {
            $calories = (int) ($data['calories'] ?? 0);
        } else {
            $calories = array_key_exists('calories', $data) && null !== $data['calories']
                ? (int) $data['calories']
                : null;
        }

        $normalized = [
            'date' => (string) $data['date'],
            'type' => $type->value,
            'name' => trim((string) ($data['name'] ?? '')),
            'duration_seconds' => $durationSeconds,
            'feeling' => $this->nullableString($data['feeling'] ?? null),
            'note' => '' === $note ? null : $note,
            'distance_km' => null,
            'pulse' => null,
            'incline_percent' => null,
            'run_surface' => null,
            'exercises' => [],
            'calories' => $calories,
            'calories_mode' => $mode,
            'legacy_calories' => $legacy,
            'calorie_weight_kg' => isset($data['calorie_weight_kg']) && '' !== $data['calorie_weight_kg']
                ? (float) $data['calorie_weight_kg']
                : null,
            'strength_character' => $this->nullableString($data['strength_character'] ?? null),
            'activity_kind' => $this->nullableString($data['activity_kind'] ?? null),
            'cardio_intensity' => $this->nullableString($data['cardio_intensity'] ?? null),
            'power_watts' => isset($data['power_watts']) && '' !== $data['power_watts']
                ? (int) $data['power_watts']
                : null,
            'incline_ignored' => (bool) ($data['incline_ignored'] ?? false),
            'calories_recalculate' => (bool) ($data['calories_recalculate'] ?? false),
        ];

        if ($type->isCardio()) {
            $inputMode = $this->nullableString($data['input_mode'] ?? null) ?? 'distance';
            $distance = null;

            if ('speed' === $inputMode && isset($data['speed_kmh']) && '' !== $data['speed_kmh']) {
                $distance = $this->metrics->distanceFromSpeed(
                    (float) $data['speed_kmh'],
                    $durationSeconds,
                );

                if (null === $distance || $distance <= 0) {
                    throw new \InvalidArgumentException('Не удалось рассчитать дистанцию по скорости.');
                }
            } elseif (isset($data['distance_km']) && '' !== $data['distance_km']) {
                $distance = (float) $data['distance_km'];
            }

            if (null !== $distance && $distance <= 0) {
                throw new \InvalidArgumentException('Дистанция должна быть больше нуля.');
            }

            $normalized['distance_km'] = $distance;
            $normalized['pulse'] = isset($data['pulse']) && '' !== $data['pulse']
                ? (int) $data['pulse']
                : null;
            $normalized['run_surface'] = WorkoutType::RUN === $type
                ? $this->nullableString($data['run_surface'] ?? null)
                : null;
            $normalized['incline_percent'] = WorkoutType::RUN === $type
                && isset($data['incline_percent'])
                && '' !== $data['incline_percent']
                    ? (float) $data['incline_percent']
                    : null;

            return $normalized;
        }

        foreach (array_values($data['exercises'] ?? []) as $exerciseIndex => $exercise) {
            $sets = [];

            foreach (array_values($exercise['sets'] ?? []) as $setIndex => $set) {
                $sets[] = [
                    'position' => $setIndex,
                    'weight_kg' => isset($set['weight_kg']) && '' !== $set['weight_kg']
                        ? (float) $set['weight_kg']
                        : null,
                    'reps' => isset($set['reps']) && '' !== $set['reps']
                        ? (int) $set['reps']
                        : null,
                    'seconds' => isset($set['seconds']) && '' !== $set['seconds']
                        ? (int) $set['seconds']
                        : null,
                ];
            }

            $normalized['exercises'][] = [
                'exercise_id' => $this->nullableString($exercise['exercise_id'] ?? null),
                'name' => trim((string) $exercise['name']),
                'mode' => (string) $exercise['mode'],
                'position' => $exerciseIndex,
                'sets' => $sets,
            ];
        }

        return $normalized;
    }

    private function nullableString(mixed $value): string|null
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
