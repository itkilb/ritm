<?php

namespace App\Containers\AppSection\Workout\UI\API\Transformers;

use App\Containers\AppSection\Workout\Enums\WorkoutType;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\Models\WorkoutExercise;
use App\Containers\AppSection\Workout\Models\WorkoutSet;
use App\Containers\AppSection\Workout\Services\WorkoutCalorieService;
use App\Containers\AppSection\Workout\Services\WorkoutMetricsCalculator;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class WorkoutTransformer extends ParentTransformer
{
    private WorkoutMetricsCalculator $metrics;
    private WorkoutCalorieService $calories;

    public function __construct()
    {
        $this->metrics = app(WorkoutMetricsCalculator::class);
        $this->calories = app(WorkoutCalorieService::class);
    }

    public function transform(Workout $workout): array
    {
        $exerciseArrays = [];
        $exercises = [];

        foreach ($workout->exercises as $exercise) {
            $exerciseArrays[] = [
                'mode' => $exercise->mode,
                'sets' => $exercise->sets->map(static fn (WorkoutSet $set): array => [
                    'weight_kg' => $set->weight_kg,
                    'reps' => $set->reps,
                    'seconds' => $set->seconds,
                ])->all(),
            ];

            $exercises[] = $this->transformExercise($exercise);
        }

        $distance = null === $workout->distance_km ? null : (float) $workout->distance_km;
        $isCardio = $workout->type instanceof WorkoutType && $workout->type->isCardio();
        $cardio = $isCardio
            ? $this->metrics->cardio($distance, (int) $workout->duration_seconds)
            : ['speed_kmh' => null, 'pace_seconds_per_km' => null, 'pace' => null];

        $calorie = $this->calories->fromWorkout($workout);

        return [
            'id' => $workout->getHashedKey(),
            'date' => $workout->date->format('Y-m-d'),
            'type' => $workout->type->value,
            'name' => $workout->name,
            'duration_seconds' => (int) $workout->duration_seconds,
            'duration_minutes' => round(((int) $workout->duration_seconds) / 60, 2),
            'calories' => $calorie->calories,
            'calories_mode' => $calorie->mode,
            'calories_status' => $calorie->status,
            'active_kcal' => $calorie->activeKcal,
            'gross_kcal' => $calorie->grossKcal,
            'calorie_method' => $calorie->method,
            'met_code' => $calorie->metCode,
            'met_value' => $calorie->metValue,
            'met_label' => $calorie->metLabel,
            'weight_kg' => $calorie->weightKg,
            'weight_source' => $calorie->weightSource,
            'catalog_version' => $calorie->catalogVersion,
            'formula_version' => $calorie->formulaVersion,
            'calorie_approximate' => $calorie->approximate,
            'incline_ignored' => $calorie->inclineIgnored,
            'calorie_inputs' => $calorie->inputs,
            'calorie_warnings' => $calorie->warnings,
            'calorie_explanation' => $this->calories->explanation($calorie),
            'activity_kind' => $workout->activity_kind,
            'strength_character' => $workout->strength_character,
            'cardio_intensity' => $workout->cardio_intensity,
            'power_watts' => null === $workout->power_watts ? null : (int) $workout->power_watts,
            'feeling' => $workout->feeling?->value,
            'note' => $workout->note,
            'distance_km' => $isCardio ? $distance : null,
            'pulse' => $isCardio && null !== $workout->pulse ? (int) $workout->pulse : null,
            'incline_percent' => WorkoutType::RUN === $workout->type && null !== $workout->incline_percent
                ? (float) $workout->incline_percent
                : null,
            'run_surface' => WorkoutType::RUN === $workout->type ? $workout->run_surface : null,
            'speed_kmh' => $isCardio ? $cardio['speed_kmh'] : null,
            'pace_seconds_per_km' => $isCardio ? $cardio['pace_seconds_per_km'] : null,
            'pace' => $isCardio ? $cardio['pace'] : null,
            'volume_kg' => $this->metrics->volumeKg($exerciseArrays),
            'exercise_count' => count($exercises),
            'set_count' => array_sum(array_map(
                static fn (array $exercise): int => count($exercise['sets']),
                $exercises,
            )),
            'exercises' => $exercises,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformExercise(WorkoutExercise $exercise): array
    {
        return [
            'id' => $exercise->getHashedKey(),
            'exercise_id' => $exercise->exercise?->getHashedKey(),
            'name' => $exercise->name,
            'mode' => $exercise->mode->value,
            'position' => (int) $exercise->position,
            'sets' => array_values($exercise->sets->map(
                static fn (WorkoutSet $set): array => [
                    'id' => $set->getHashedKey(),
                    'position' => (int) $set->position,
                    'weight_kg' => null === $set->weight_kg ? null : (float) $set->weight_kg,
                    'reps' => null === $set->reps ? null : (int) $set->reps,
                    'seconds' => null === $set->seconds ? null : (int) $set->seconds,
                ],
            )->all()),
        ];
    }
}
