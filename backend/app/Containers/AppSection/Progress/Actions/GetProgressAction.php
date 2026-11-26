<?php

namespace App\Containers\AppSection\Progress\Actions;

use App\Containers\AppSection\Exercise\Data\ExerciseCatalog;
use App\Containers\AppSection\Exercise\Tasks\ResolveExerciseForUserTask;
use App\Containers\AppSection\Progress\Tasks\ResolveProgressPeriodTask;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Containers\AppSection\WeightEntry\Tasks\GetLatestWeightEntryTask;
use App\Containers\AppSection\WeightEntry\Tasks\GetWeightEntriesTask;
use App\Containers\AppSection\Workout\Enums\WorkoutType;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\Models\WorkoutExercise;
use App\Containers\AppSection\Workout\Models\WorkoutSet;
use App\Containers\AppSection\Workout\Services\WorkoutTotals;
use App\Containers\AppSection\Workout\Tasks\GetWorkoutsForDateRangeTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class GetProgressAction extends ParentAction
{
    private const CARDIO_KIND_ORDER = [
        'outdoor_bike',
        'stationary_bike',
        'elliptical',
        'other',
        'unknown',
    ];

    private const CARDIO_SPEED_KINDS = ['outdoor_bike', 'stationary_bike'];

    public function __construct(
        private readonly ResolveProgressPeriodTask $resolveProgressPeriodTask,
        private readonly GetWeightEntriesTask $getWeightEntriesTask,
        private readonly GetLatestWeightEntryTask $getLatestWeightEntryTask,
        private readonly GetWorkoutsForDateRangeTask $getWorkoutsForDateRangeTask,
        private readonly WorkoutTotals $workoutTotals,
        private readonly ResolveExerciseForUserTask $resolveExerciseForUserTask,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        User $user,
        string|null $period,
        string|null $date,
        string|null $exerciseId,
    ): array {
        $range = $this->resolveProgressPeriodTask->run($user, $period, $date);
        $start = $range['start_date'];
        $end = $range['end_date'];

        $weight = $this->weightSection($user, $start, $end);
        $workouts = $this->workoutSection($user, $start, $end);
        $history = null === $exerciseId || '' === $exerciseId
            ? null
            : $this->exerciseHistory($user, $exerciseId, $start, $end);

        return [
            'period' => $range['period'],
            'start_date' => $start,
            'end_date' => $end,
            'timezone' => $range['timezone'],
            'today' => $range['today'],
            'weight' => $weight,
            'workouts' => $workouts,
            'exercise_history' => $history,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function weightSection(User $user, string $start, string $end): array
    {
        // Six extra days complete the first average window; a null limit keeps long histories whole.
        $all = $this->getWeightEntriesTask->run(
            $user,
            $this->addDays($start, -6),
            $end,
            null,
        );

        $all = $all->values();
        $entries = $all->filter(
            static fn (WeightEntry $entry): bool => $entry->date->format('Y-m-d') >= $start,
        )->values();

        $average = [];
        $left = 0;
        $right = 0;
        $windowCount = 0;
        $windowSum = 0.0;
        $allCount = $all->count();

        // Linear 7-day window over the full range; at least two measurements are required.
        foreach ($entries as $entry) {
            $entryDate = $entry->date->format('Y-m-d');
            $windowStart = $this->addDays($entryDate, -6);

            while ($right < $allCount) {
                $candidate = $all[$right];

                if ($candidate->date->format('Y-m-d') > $entryDate) {
                    break;
                }

                $windowSum += (float) $candidate->weight_kg;
                ++$windowCount;
                ++$right;
            }

            while ($left < $right) {
                $candidate = $all[$left];

                if ($candidate->date->format('Y-m-d') >= $windowStart) {
                    break;
                }

                $windowSum -= (float) $candidate->weight_kg;
                --$windowCount;
                ++$left;
            }

            if ($windowCount < 2) {
                continue;
            }

            $average[] = [
                'date' => $entryDate,
                'weight_kg' => round($windowSum / $windowCount, 3),
                'measurements' => $windowCount,
            ];
        }

        $latest = $this->getLatestWeightEntryTask->run($user);
        $first = $entries->first();
        $last = $entries->last();
        $change = null;

        if (null !== $first && null !== $last && $entries->count() >= 2) {
            $change = round((float) $last->weight_kg - (float) $first->weight_kg, 3);
        }

        $profile = UserProfile::query()->where('user_id', $user->getKey())->first();

        return [
            'entries' => array_values(array_map(
                static fn (WeightEntry $entry): array => [
                    'id' => $entry->getHashedKey(),
                    'date' => $entry->date->format('Y-m-d'),
                    'weight_kg' => round((float) $entry->weight_kg, 3),
                ],
                $entries->all(),
            )),
            'average' => $average,
            'latest' => null === $latest ? null : [
                'id' => $latest->getHashedKey(),
                'date' => $latest->date->format('Y-m-d'),
                'weight_kg' => round((float) $latest->weight_kg, 3),
            ],
            'change_kg' => $change,
            'profile_weight_kg' => null === $profile?->weight_kg
                ? null
                : round((float) $profile->weight_kg, 3),
            'target_weight_kg' => null === $profile?->target_weight_kg
                ? null
                : round((float) $profile->target_weight_kg, 3),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutSection(User $user, string $start, string $end): array
    {
        /** @var Collection<int, Workout> $workouts */
        $workouts = $this->getWorkoutsForDateRangeTask->run($user, $start, $end);
        $totals = $this->workoutTotals->forWorkouts($workouts);

        $byType = ['strength' => 0, 'run' => 0, 'cardio' => 0];
        $run = [
            'count' => 0,
            'duration_seconds' => 0,
            'distance_km' => 0.0,
            'distance_duration_seconds' => 0,
        ];
        $cardio = ['count' => 0, 'duration_seconds' => 0, 'distance_km' => 0.0];

        foreach ($workouts as $workout) {
            $type = $workout->type->value;
            $byType[$type] = ($byType[$type] ?? 0) + 1;

            if (WorkoutType::RUN === $workout->type) {
                ++$run['count'];
                $run['duration_seconds'] += (int) $workout->duration_seconds;
                $distance = null === $workout->distance_km ? null : (float) $workout->distance_km;

                // Legacy runs without distance must not dilute the aggregate pace.
                if (null !== $distance && $distance > 0) {
                    $run['distance_km'] += $distance;
                    $run['distance_duration_seconds'] += (int) $workout->duration_seconds;
                }
            } elseif (WorkoutType::CARDIO === $workout->type) {
                ++$cardio['count'];
                $cardio['duration_seconds'] += (int) $workout->duration_seconds;
                $cardio['distance_km'] += (float) ($workout->distance_km ?? 0);
            }
        }

        $weeks = [];
        $monday = $this->mondayOf($start);

        while ($monday <= $end) {
            $weekEnd = $this->addDays($monday, 6);
            $weekWorkouts = $workouts->filter(
                static fn (Workout $workout): bool => $workout->date->format('Y-m-d') >= $start
                    && $workout->date->format('Y-m-d') <= $end
                    && $workout->date->format('Y-m-d') >= $monday
                    && $workout->date->format('Y-m-d') <= $weekEnd,
            );
            $weekTotals = $this->workoutTotals->forWorkouts($weekWorkouts);

            $weeks[] = [
                'week_start' => $monday,
                'week_end' => $weekEnd,
                'count' => $weekTotals['count'],
                'minutes' => $weekTotals['minutes'],
                'calories' => $weekTotals['calories'],
                'distance_km' => $weekTotals['distance_km'],
            ];

            $monday = $this->addDays($monday, 7);
        }

        $runDistance = round($run['distance_km'], 3);
        $runMinutes = round($run['duration_seconds'] / 60, 2);
        $runPace = $runDistance > 0 && $run['distance_duration_seconds'] > 0
            ? (int) round($run['distance_duration_seconds'] / $runDistance)
            : null;

        $cardioDistance = round($cardio['distance_km'], 3);
        $cardioMinutes = round($cardio['duration_seconds'] / 60, 2);

        return [
            'totals' => [
                'count' => $totals['count'],
                'minutes' => $totals['minutes'],
                'duration_seconds' => $totals['duration_seconds'],
                'calories' => $totals['calories'],
                'uncalculated_count' => $totals['uncalculated_count'],
                'distance_km' => $totals['distance_km'],
            ],
            'weeks' => $weeks,
            'by_type' => $byType,
            'run' => [
                'count' => $run['count'],
                'minutes' => $runMinutes,
                'distance_km' => $runDistance,
                'pace_seconds_per_km' => $runPace,
                'pace' => null === $runPace ? null : $this->formatPace($runPace),
            ],
            'cardio' => [
                'count' => $cardio['count'],
                'minutes' => $cardioMinutes,
                'distance_km' => $cardioDistance,
                'speed_kmh' => null,
                'kinds' => $this->cardioKinds($workouts),
            ],
        ];
    }

    /**
     * @param Collection<int, Workout> $workouts
     *
     * @return array<int, array<string, mixed>>
     */
    private function cardioKinds(Collection $workouts): array
    {
        /** @var array<string, array<string, mixed>> $buckets */
        $buckets = [];

        foreach ($workouts as $workout) {
            if (WorkoutType::CARDIO !== $workout->type) {
                continue;
            }

            $kind = $workout->activity_kind;

            if (null === $kind || '' === trim($kind)) {
                $kind = 'unknown';
            }

            if (!isset($buckets[$kind])) {
                $buckets[$kind] = [
                    'activity_kind' => $kind,
                    'count' => 0,
                    'duration_seconds' => 0,
                    'distance_km' => 0.0,
                    'distance_duration_seconds' => 0,
                ];
            }

            ++$buckets[$kind]['count'];
            $buckets[$kind]['duration_seconds'] += (int) $workout->duration_seconds;
            $distance = null === $workout->distance_km ? null : (float) $workout->distance_km;

            if (null !== $distance && $distance > 0) {
                $buckets[$kind]['distance_km'] += $distance;
                $buckets[$kind]['distance_duration_seconds'] += (int) $workout->duration_seconds;
            }
        }

        $ordered = [];

        foreach (self::CARDIO_KIND_ORDER as $kind) {
            if (isset($buckets[$kind])) {
                $ordered[$kind] = $buckets[$kind];
                unset($buckets[$kind]);
            }
        }

        ksort($buckets);

        foreach ($buckets as $kind => $bucket) {
            $ordered[$kind] = $bucket;
        }

        $kinds = [];

        foreach ($ordered as $kind => $bucket) {
            $distance = round((float) $bucket['distance_km'], 3);
            $speed = null;

            if (
                in_array($kind, self::CARDIO_SPEED_KINDS, true)
                && $distance > 0
                && $bucket['distance_duration_seconds'] > 0
            ) {
                $speed = round(
                    $distance / ($bucket['distance_duration_seconds'] / 3600),
                    2,
                );
            }

            $kinds[] = [
                'activity_kind' => $kind,
                'count' => $bucket['count'],
                'minutes' => round($bucket['duration_seconds'] / 60, 2),
                'distance_km' => $distance,
                'speed_kmh' => $speed,
            ];
        }

        return $kinds;
    }

    /**
     * @return array<string, mixed>
     */
    private function exerciseHistory(
        User $user,
        string $exerciseId,
        string $start,
        string $end,
    ): array {
        $exercise = $this->resolveExerciseForUserTask->run($user, $exerciseId);

        if (null === $exercise) {
            abort(404, 'Упражнение не найдено.');
        }

        /** @var Collection<int, WorkoutExercise> $rows */
        $rows = WorkoutExercise::query()
            ->where('exercise_id', $exercise->getKey())
            ->whereHas('workout', static function ($query) use ($user, $start, $end): void {
                $query->where('user_id', $user->getKey())
                    ->whereDate('date', '>=', $start)
                    ->whereDate('date', '<=', $end);
            })
            ->with(['workout', 'sets'])
            ->get()
            ->sortBy(static fn (WorkoutExercise $row): string => sprintf(
                '%s|%010d|%04d',
                $row->workout->date->format('Y-m-d'),
                $row->workout->getKey(),
                $row->position,
            ))
            ->values();

        $points = [];

        foreach ($rows as $row) {
            $point = $this->pointFor($row);

            if (null !== $point) {
                $points[] = $point;
            }
        }

        $best = $this->bestPoint($exercise->measurement_type, $points);

        return [
            'exercise' => [
                'id' => $exercise->getHashedKey(),
                'name' => $exercise->name,
                'muscle_group' => $exercise->muscle_group,
                'equipment' => $exercise->equipment,
                'measurement_type' => $exercise->measurement_type,
            ],
            'points' => $points,
            'summary' => array_merge(['count' => count($points)], $best),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pointFor(WorkoutExercise $row): array|null
    {
        $sets = $row->sets;

        if ($sets->isEmpty()) {
            return null;
        }

        $mode = $row->mode->value;
        $weight = null;
        $reps = null;
        $seconds = null;

        if ('weighted' === $mode || 'bodyweight' === $mode) {
            /** @var WorkoutSet|null $best */
            $best = null;

            foreach ($sets as $set) {
                $candidateWeight = (float) ($set->weight_kg ?? 0);
                $candidateReps = (int) ($set->reps ?? 0);

                if (
                    null === $best
                    || $candidateWeight > (float) ($best->weight_kg ?? 0)
                    || (
                        abs($candidateWeight - (float) ($best->weight_kg ?? 0)) < 0.000_000_1
                        && $candidateReps > (int) ($best->reps ?? 0)
                    )
                ) {
                    $best = $set;
                }
            }

            $weight = round((float) ($best?->weight_kg ?? 0), 3);
            $reps = null === $best?->reps ? null : (int) $best->reps;
        } elseif ('reps' === $mode) {
            $reps = (int) $sets->max(static fn (WorkoutSet $set): int => (int) $set->reps);
        } else {
            $seconds = (int) $sets->max(static fn (WorkoutSet $set): int => (int) $set->seconds);
        }

        return [
            'date' => $row->workout->date->format('Y-m-d'),
            'workout_id' => $row->workout->getHashedKey(),
            'workout_name' => $row->workout->name,
            'exercise_name' => $row->name,
            'mode' => $mode,
            'weight_kg' => $weight,
            'reps' => $reps,
            'seconds' => $seconds,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $points
     *
     * @return array{best_weight_kg: float|null, best_reps: int|null, best_seconds: int|null}
     */
    private function bestPoint(string $measurementType, array $points): array
    {
        $bestWeight = null;
        $bestReps = null;
        $bestSeconds = null;

        foreach ($points as $point) {
            $weight = null === $point['weight_kg'] ? null : (float) $point['weight_kg'];
            $reps = null === $point['reps'] ? null : (int) $point['reps'];
            $seconds = null === $point['seconds'] ? null : (int) $point['seconds'];

            if (ExerciseCatalog::TYPE_WEIGHTED === $measurementType
                || ExerciseCatalog::TYPE_BODYWEIGHT === $measurementType
            ) {
                if (null === $weight) {
                    continue;
                }

                if (
                    null === $bestWeight
                    || $weight > $bestWeight
                    || (abs($weight - $bestWeight) < 0.000_000_1 && (int) $reps > (int) $bestReps)
                ) {
                    $bestWeight = $weight;
                    $bestReps = $reps;
                }

                continue;
            }

            if (ExerciseCatalog::TYPE_REPS === $measurementType) {
                if (null !== $reps && (null === $bestReps || $reps > $bestReps)) {
                    $bestReps = $reps;
                }

                continue;
            }

            if (null !== $seconds && (null === $bestSeconds || $seconds > $bestSeconds)) {
                $bestSeconds = $seconds;
            }
        }

        return [
            'best_weight_kg' => null === $bestWeight ? null : round($bestWeight, 3),
            'best_reps' => $bestReps,
            'best_seconds' => $bestSeconds,
        ];
    }

    private function formatPace(int $seconds): string
    {
        return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
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
