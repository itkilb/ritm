<?php

namespace App\Containers\AppSection\Progress\Tests\Functional\API;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\Exercise\Models\Exercise;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Tests\ProductApiTestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ProgressTest extends ProductApiTestCase
{
    public function testGuestCannotReadProgressAndInvalidQueriesAreRejected(): void
    {
        $this->getJson('/v1/progress')->assertUnauthorized();

        $this->actingAsUser($this->createCompletedUser());

        $this->getJson('/v1/progress?period=week')->assertUnprocessable();
        $this->getJson('/v1/progress?date=2025-02-30')->assertUnprocessable();
        $this->getJson('/v1/progress?date=15.01.2025')->assertUnprocessable();
        $this->getJson('/v1/progress?exercise=unknown-id')->assertNotFound();
    }

    public function testEmptyAccountReturnsExplicitZerosAndNulls(): void
    {
        $user = User::factory()->createOne();
        $this->actingAsUser($user);

        $response = $this->getJson('/v1/progress?period=month&date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.period', 'month')
            ->assertJsonPath('data.start_date', '2025-01-01')
            ->assertJsonPath('data.end_date', '2025-01-15')
            ->assertJsonPath('data.weight.entries', [])
            ->assertJsonPath('data.weight.average', [])
            ->assertJsonPath('data.weight.latest', null)
            ->assertJsonPath('data.weight.change_kg', null)
            ->assertJsonPath('data.weight.profile_weight_kg', null)
            ->assertJsonPath('data.weight.target_weight_kg', null)
            ->assertJsonPath('data.workouts.totals.count', 0)
            ->assertJsonPath('data.workouts.totals.minutes', 0)
            ->assertJsonPath('data.exercise_history', null);

        $weeks = $response->json('data.workouts.weeks');
        $this->assertSame(['2024-12-30', '2025-01-06', '2025-01-13'], array_column($weeks, 'week_start'));
        $this->assertSame([0, 0, 0], array_column($weeks, 'count'));
    }

    public function testPeriodsAreCalendarAlignedAndAllUsesEarliestRecord(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/weight-entries', [
            'date' => '2024-12-20',
            'weight_kg' => 82,
        ])->assertCreated();

        $this->getJson('/v1/progress?period=month&date=2025-01-15')
            ->assertJsonPath('data.start_date', '2025-01-01');
        $this->getJson('/v1/progress?period=3months&date=2025-01-15')
            ->assertJsonPath('data.start_date', '2024-11-01');
        $this->getJson('/v1/progress?period=year&date=2025-01-15')
            ->assertJsonPath('data.start_date', '2025-01-01');
        $this->getJson('/v1/progress?period=all&date=2025-01-15')
            ->assertJsonPath('data.start_date', '2024-12-20');

        $this->getJson('/v1/progress?period=month&date=2025-03-01')
            ->assertJsonPath('data.end_date', '2025-01-15');
    }

    public function testWeightUsesRealPointsMovingAverageAndPeriodChange(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        foreach ([
            ['date' => '2025-01-01', 'weight_kg' => 80],
            ['date' => '2025-01-08', 'weight_kg' => 82],
            ['date' => '2025-01-10', 'weight_kg' => 81],
        ] as $entry) {
            $this->postJson('/v1/weight-entries', $entry)->assertCreated();
        }

        $this->putJson('/v1/me/target-weight', ['target_weight_kg' => 75])
            ->assertOk();

        $response = $this->getJson('/v1/progress?period=month&date=2025-01-10')
            ->assertOk()
            ->assertJsonPath('data.weight.change_kg', 1)
            ->assertJsonPath('data.weight.target_weight_kg', 75)
            ->assertJsonPath('data.weight.latest.date', '2025-01-15')
            ->assertJsonPath('data.weight.latest.weight_kg', 80);

        $entries = $response->json('data.weight.entries');
        $this->assertSame(['2025-01-01', '2025-01-08', '2025-01-10'], array_column($entries, 'date'));

        $average = $response->json('data.weight.average');
        $this->assertCount(1, $average);
        $this->assertSame('2025-01-10', $average[0]['date']);
        $this->assertSame(81.5, $average[0]['weight_kg']);
        $this->assertSame(2, $average[0]['measurements']);

        $this->getJson('/v1/progress?period=month&date=2025-01-01')
            ->assertJsonPath('data.weight.change_kg', null);
    }

    public function testWeeklyBucketsCountOnlyWorkoutsAndIncludeZeroWeeks(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->workout($user, '2025-01-06', 'strength', 1800, 100);
        $this->workout($user, '2025-01-13', 'strength', 3600, 200);
        ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Прогулка',
            'calories' => 90,
            'steps' => 2000,
            'minutes' => 45,
            'distance_km' => 2,
        ]);

        $response = $this->getJson('/v1/progress?period=month&date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.workouts.totals.count', 2)
            ->assertJsonPath('data.workouts.totals.minutes', 90)
            ->assertJsonPath('data.workouts.totals.calories', 300);

        $weeks = $response->json('data.workouts.weeks');
        $this->assertSame(['2024-12-30', '2025-01-06', '2025-01-13'], array_column($weeks, 'week_start'));
        $this->assertSame([0, 1, 1], array_column($weeks, 'count'));
        $this->assertSame([0, 30, 60], array_column($weeks, 'minutes'));
        $this->assertSame([0, 100, 200], array_column($weeks, 'calories'));
    }

    public function testRunAndOtherCardioStaySeparateAndRunDistanceOnlyInWeekTotals(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->workout($user, '2025-01-06', 'run', 1800, 300, 5);
        $this->workout($user, '2025-01-13', 'run', 1200, 200, 3);
        $this->workout($user, '2025-01-07', 'cardio', 1800, 250, 12);

        $response = $this->getJson('/v1/progress?period=month&date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.workouts.by_type.run', 2)
            ->assertJsonPath('data.workouts.by_type.cardio', 1)
            ->assertJsonPath('data.workouts.run.distance_km', 8)
            ->assertJsonPath('data.workouts.run.minutes', 50)
            ->assertJsonPath('data.workouts.run.pace_seconds_per_km', 375)
            ->assertJsonPath('data.workouts.run.pace', '6:15')
            ->assertJsonPath('data.workouts.cardio.distance_km', 12)
            ->assertJsonPath('data.workouts.cardio.minutes', 30)
            ->assertJsonPath('data.workouts.cardio.speed_kmh', null)
            ->assertJsonPath('data.workouts.totals.distance_km', 8);

        $kinds = $response->json('data.workouts.cardio.kinds');
        $this->assertCount(1, $kinds);
        $this->assertSame('unknown', $kinds[0]['activity_kind']);
        $this->assertSame(12.0, (float) $kinds[0]['distance_km']);
        $this->assertNull($kinds[0]['speed_kmh']);

        $weeks = $response->json('data.workouts.weeks');
        $this->assertSame([0, 5, 3], array_column($weeks, 'distance_km'));
    }

    public function testCardioKindsStaySeparateAndRunPaceIgnoresRunsWithoutDistance(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->workout($user, '2025-01-06', 'cardio', 1800, 200, 12, 'outdoor_bike');
        $this->workout($user, '2025-01-07', 'cardio', 1800, 200, 20, 'stationary_bike');
        $this->workout($user, '2025-01-08', 'cardio', 1800, 200, 3, 'elliptical');
        $this->workout($user, '2025-01-09', 'cardio', 1800, 200, 2, 'other');
        $this->workout($user, '2025-01-10', 'cardio', 1800, 200, 1, null);
        $this->workout($user, '2025-01-11', 'run', 1800, 300, 5);
        $this->workout($user, '2025-01-12', 'run', 600, 50);

        $response = $this->getJson('/v1/progress?period=month&date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.workouts.run.count', 2)
            ->assertJsonPath('data.workouts.run.minutes', 40)
            ->assertJsonPath('data.workouts.run.distance_km', 5)
            ->assertJsonPath('data.workouts.run.pace_seconds_per_km', 360)
            ->assertJsonPath('data.workouts.run.pace', '6:00')
            ->assertJsonPath('data.workouts.cardio.count', 5)
            ->assertJsonPath('data.workouts.cardio.distance_km', 38)
            ->assertJsonPath('data.workouts.cardio.speed_kmh', null);

        $kinds = collect($response->json('data.workouts.cardio.kinds'))
            ->keyBy('activity_kind');

        $this->assertSame(
            ['outdoor_bike', 'stationary_bike', 'elliptical', 'other', 'unknown'],
            $kinds->keys()->all(),
        );
        $this->assertSame(1, $kinds['outdoor_bike']['count']);
        $this->assertSame(24.0, (float) $kinds['outdoor_bike']['speed_kmh']);
        $this->assertSame(40.0, (float) $kinds['stationary_bike']['speed_kmh']);
        $this->assertSame(3.0, (float) $kinds['elliptical']['distance_km']);
        $this->assertNull($kinds['elliptical']['speed_kmh']);
        $this->assertNull($kinds['other']['speed_kmh']);
        $this->assertSame(1, $kinds['unknown']['count']);
        $this->assertNull($kinds['unknown']['speed_kmh']);
    }

    public function testLongWeightHistoryIsAggregatedWithoutTheListPageLimit(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $last = CarbonImmutable::createFromFormat('!Y-m-d', '2025-01-15', 'UTC');
        $rows = [];

        for ($offset = 1004; $offset >= 1; --$offset) {
            $rows[] = [
                'user_id' => $user->getKey(),
                'date' => $last->subDays($offset)->format('Y-m-d'),
                'weight_kg' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            WeightEntry::query()->insert($chunk);
        }

        $response = $this->getJson('/v1/progress?period=all&date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.weight.change_kg', -20)
            ->assertJsonPath('data.weight.latest.date', '2025-01-15')
            ->assertJsonPath('data.weight.latest.weight_kg', 80);

        $entries = $response->json('data.weight.entries');
        $this->assertCount(1005, $entries);
        $this->assertSame($last->subDays(1004)->format('Y-m-d'), $entries[0]['date']);
        $this->assertSame('2025-01-15', $entries[1004]['date']);
        $this->assertSame(100.0, (float) $entries[0]['weight_kg']);
        $this->assertSame(80.0, (float) $entries[1004]['weight_kg']);

        $average = $response->json('data.weight.average');
        $this->assertCount(1004, $average);
        $this->assertSame('2025-01-15', $average[1003]['date']);
        $this->assertSame(7, $average[1003]['measurements']);
        $this->assertSame(97.143, $average[1003]['weight_kg']);
    }

    public function testExerciseHistoryBestSetUsesSameSetAndKeepsModesSeparate(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $bench = Exercise::query()->where('slug', 'bench-press-barbell')->firstOrFail();
        $crunches = Exercise::query()->where('slug', 'crunches')->firstOrFail();
        $plank = Exercise::query()->where('slug', 'plank')->firstOrFail();

        $first = $this->workout($user, '2025-01-10', 'strength', 2700, 150);
        $this->exercise($first, $bench, 'weighted', [
            ['weight_kg' => 60, 'reps' => 10],
            ['weight_kg' => 70, 'reps' => 5],
            ['weight_kg' => 70, 'reps' => 8],
        ]);

        $second = $this->workout($user, '2025-01-12', 'strength', 1800, 100);
        $this->exercise($second, $bench, 'weighted', [
            ['weight_kg' => 0, 'reps' => 12],
        ]);
        $this->exercise($second, $crunches, 'reps', [
            ['reps' => 20],
            ['reps' => 25],
        ]);
        $this->exercise($second, $plank, 'time', [
            ['seconds' => 45],
            ['seconds' => 60],
        ]);

        $benchHistory = $this->getJson('/v1/progress?period=month&date=2025-01-15&exercise=' . $bench->getHashedKey())
            ->assertOk()
            ->assertJsonPath('data.exercise_history.exercise.measurement_type', 'weighted')
            ->assertJsonPath('data.exercise_history.summary.best_weight_kg', 70)
            ->assertJsonPath('data.exercise_history.summary.best_reps', 8)
            ->json('data.exercise_history');

        $this->assertCount(2, $benchHistory['points']);
        $this->assertSame(70.0, (float) $benchHistory['points'][0]['weight_kg']);
        $this->assertSame(8, $benchHistory['points'][0]['reps']);
        $this->assertSame(0.0, (float) $benchHistory['points'][1]['weight_kg']);
        $this->assertSame(12, $benchHistory['points'][1]['reps']);
        $this->assertSame($first->getHashedKey(), $benchHistory['points'][0]['workout_id']);

        $repsHistory = $this->getJson('/v1/progress?period=month&date=2025-01-15&exercise=' . $crunches->getHashedKey())
            ->assertOk()
            ->json('data.exercise_history');
        $this->assertSame(25, $repsHistory['summary']['best_reps']);
        $this->assertNull($repsHistory['points'][0]['weight_kg']);
        $this->assertNull($repsHistory['points'][0]['seconds']);

        $timeHistory = $this->getJson('/v1/progress?period=month&date=2025-01-15&exercise=' . $plank->getHashedKey())
            ->assertOk()
            ->json('data.exercise_history');
        $this->assertSame(60, $timeHistory['summary']['best_seconds']);
        $this->assertNull($timeHistory['points'][0]['weight_kg']);
        $this->assertNull($timeHistory['points'][0]['reps']);
    }

    public function testProgressIsIsolatedPerUserAndLegacyUnlinkedExercisesAreNotMerged(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($user);

        $bench = Exercise::query()->where('slug', 'bench-press-barbell')->firstOrFail();

        $legacy = $this->workout($user, '2025-01-10', 'strength', 1800, 100);
        $legacy->exercises()->create([
            'exercise_id' => null,
            'name' => 'Жим штанги лёжа',
            'mode' => 'weighted',
            'position' => 0,
        ])->sets()->create([
            'position' => 0,
            'weight_kg' => 90,
            'reps' => 1,
        ]);

        $this->workout($other, '2025-01-11', 'strength', 1800, 100);
        $otherWorkout = Workout::query()->where('user_id', $other->getKey())->firstOrFail();
        $this->exercise($otherWorkout, $bench, 'weighted', [
            ['weight_kg' => 120, 'reps' => 1],
        ]);

        $response = $this->getJson('/v1/progress?period=month&date=2025-01-15&exercise=' . $bench->getHashedKey())
            ->assertOk()
            ->json('data');

        $this->assertSame([], $response['exercise_history']['points']);
        $this->assertSame(1, $response['workouts']['totals']['count']);
        $this->assertSame(['2025-01-15'], array_column($response['weight']['entries'], 'date'));
    }

    /**
     * @param array<int, array<string, mixed>> $sets
     */
    private function workout(
        User $user,
        string $date,
        string $type,
        int $durationSeconds,
        int $calories,
        float|null $distance = null,
        string|null $activityKind = null,
    ): Workout {
        return Workout::query()->create([
            'user_id' => $user->getKey(),
            'date' => $date,
            'type' => $type,
            'name' => 'Тренировка',
            'duration_seconds' => $durationSeconds,
            'calories' => $calories,
            'calories_mode' => 'manual',
            'calories_status' => 'manual',
            'distance_km' => $distance,
            'activity_kind' => $activityKind,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $sets
     */
    private function exercise(
        Workout $workout,
        Exercise $exercise,
        string $mode,
        array $sets,
    ): void {
        $row = $workout->exercises()->create([
            'exercise_id' => $exercise->getKey(),
            'name' => $exercise->name,
            'mode' => $mode,
            'position' => $workout->exercises()->count(),
        ]);

        foreach ($sets as $index => $set) {
            $row->sets()->create(array_merge([
                'position' => $index,
                'weight_kg' => null,
                'reps' => null,
                'seconds' => null,
            ], $set));
        }
    }
}
