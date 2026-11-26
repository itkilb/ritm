<?php

namespace App\Containers\AppSection\Workout\Tests\Functional\API;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\Models\WorkoutExercise;
use App\Containers\AppSection\Workout\Models\WorkoutSet;
use App\Ship\Tests\ProductApiTestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WorkoutTest extends ProductApiTestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function strengthPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'strength',
            'date' => '2025-01-15',
            'name' => 'Верх тела',
            'duration_seconds' => 3300,
            'calories' => 320,
            'feeling' => 'comfortable',
            'note' => 'Хорошая тренировка',
            'exercises' => [
                [
                    'name' => 'Жим лёжа',
                    'mode' => 'weighted',
                    'sets' => [
                        ['weight_kg' => 60, 'reps' => 10],
                        ['weight_kg' => 62.5, 'reps' => 8],
                    ],
                ],
                [
                    'name' => 'Планка',
                    'mode' => 'time',
                    'sets' => [
                        ['seconds' => 45],
                    ],
                ],
            ],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function runPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'run',
            'date' => '2025-01-15',
            'name' => 'Пробежка',
            'duration_seconds' => 1890,
            'calories' => 300,
            'feeling' => 'easy',
            'note' => null,
            'input_mode' => 'distance',
            'distance_km' => 5.2,
            'pulse' => 145,
            'incline_percent' => 2.5,
            'exercises' => [],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function cardioPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'cardio',
            'date' => '2025-01-15',
            'name' => 'Велосипед',
            'duration_seconds' => 1800,
            'calories' => 250,
            'feeling' => null,
            'note' => null,
            'input_mode' => 'distance',
            'distance_km' => 12,
            'pulse' => 135,
            'exercises' => [],
        ], $overrides);
    }

    public function testGuestCannotUseWorkoutApi(): void
    {
        $this->getJson('/v1/workouts')->assertUnauthorized();
        $this->getJson('/v1/workouts/abc')->assertUnauthorized();
        $this->postJson('/v1/workouts', $this->strengthPayload())->assertUnauthorized();
        $this->putJson('/v1/workouts/abc', $this->strengthPayload())->assertUnauthorized();
        $this->deleteJson('/v1/workouts/abc')->assertUnauthorized();

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testStateChangingRequestsWithoutCsrfReturnJson419(): void
    {
        $this->forceCsrfValidation();
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        foreach (['post' => '/v1/workouts', 'put' => '/v1/workouts/abc'] as $method => $uri) {
            $response = $this->{$method}($uri, $this->strengthPayload(), ['Accept' => 'text/html']);
            $response->assertStatus(419)->assertJsonStructure(['message']);
            $this->assertJsonContentType($response);
        }

        $deleteResponse = $this->delete('/v1/workouts/abc', ['Accept' => 'text/html']);
        $deleteResponse->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertJsonContentType($deleteResponse);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testCreatesNormalizedStrengthWorkoutAndIgnoresClientInternals(): void
    {
        $user = $this->createCompletedUser();
        $other = User::factory()->createOne();
        $this->actingAsUser($user);

        $response = $this->postJson('/v1/workouts', $this->strengthPayload([
            'name' => '  Верх тела  ',
            'user_id' => $other->getKey(),
            'id' => 999,
            'created_at' => '1999-01-01 00:00:00',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.type', 'strength')
            ->assertJsonPath('data.name', 'Верх тела')
            ->assertJsonPath('data.date', '2025-01-15')
            ->assertJsonPath('data.duration_seconds', 3300)
            ->assertJsonPath('data.duration_minutes', 55)
            ->assertJsonPath('data.calories', 320)
            ->assertJsonPath('data.feeling', 'comfortable')
            ->assertJsonPath('data.distance_km', null)
            ->assertJsonPath('data.speed_kmh', null)
            ->assertJsonPath('data.exercise_count', 2)
            ->assertJsonPath('data.set_count', 3)
            ->assertJsonPath('data.volume_kg', 1100)
            ->assertJsonPath('data.exercises.0.name', 'Жим лёжа')
            ->assertJsonPath('data.exercises.0.mode', 'weighted')
            ->assertJsonPath('data.exercises.0.sets.0.weight_kg', 60)
            ->assertJsonPath('data.exercises.0.sets.0.reps', 10)
            ->assertJsonPath('data.exercises.0.sets.0.seconds', null)
            ->assertJsonPath('data.exercises.1.mode', 'time')
            ->assertJsonPath('data.exercises.1.sets.0.seconds', 45);

        $workout = Workout::query()->firstOrFail();
        $this->assertSame($user->getKey(), $workout->user_id);
        $this->assertSame(0, Workout::query()->where('user_id', $other->getKey())->count());
        $this->assertSame(2, WorkoutExercise::query()->where('workout_id', $workout->getKey())->count());
        $this->assertSame(3, WorkoutSet::query()->count());

        $this->getJson('/v1/workouts/' . $response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.id', $response->json('data.id'))
            ->assertJsonPath('data.exercises.0.name', 'Жим лёжа');
    }

    public function testAllExerciseAccountingModesAreStoredAndReturned(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->postJson('/v1/workouts', $this->strengthPayload([
            'exercises' => [
                [
                    'name' => 'Приседания',
                    'mode' => 'weighted',
                    'sets' => [['weight_kg' => 80, 'reps' => 5]],
                ],
                [
                    'name' => 'Отжимания',
                    'mode' => 'bodyweight',
                    'sets' => [['weight_kg' => 5, 'reps' => 12]],
                ],
                [
                    'name' => 'Подтягивания',
                    'mode' => 'reps',
                    'sets' => [['reps' => 8]],
                ],
                [
                    'name' => 'Планка',
                    'mode' => 'time',
                    'sets' => [['seconds' => 60]],
                ],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.exercises.0.mode', 'weighted')
            ->assertJsonPath('data.exercises.1.mode', 'bodyweight')
            ->assertJsonPath('data.exercises.1.sets.0.weight_kg', 5)
            ->assertJsonPath('data.exercises.2.mode', 'reps')
            ->assertJsonPath('data.exercises.2.sets.0.weight_kg', null)
            ->assertJsonPath('data.exercises.3.mode', 'time')
            ->assertJsonPath('data.exercises.3.sets.0.reps', null)
            ->assertJsonPath('data.exercise_count', 4)
            ->assertJsonPath('data.set_count', 4);
    }

    public function testRunDistanceModeComputesSpeedPaceAndOptionalMetrics(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->postJson('/v1/workouts', $this->runPayload());

        $response->assertCreated()
            ->assertJsonPath('data.type', 'run')
            ->assertJsonPath('data.distance_km', 5.2)
            ->assertJsonPath('data.duration_seconds', 1890)
            ->assertJsonPath('data.speed_kmh', 9.9)
            ->assertJsonPath('data.pace_seconds_per_km', 363)
            ->assertJsonPath('data.pace', '6:03')
            ->assertJsonPath('data.pulse', 145)
            ->assertJsonPath('data.incline_percent', 2.5)
            ->assertJsonPath('data.exercise_count', 0);

        $workout = Workout::query()->firstOrFail();
        $this->assertSame('run', $workout->type->value);
        $this->assertSame(5.2, $workout->distance_km);
    }

    public function testRunSpeedModeComputesStoredDistanceOnTheServer(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->postJson('/v1/workouts', $this->runPayload([
            'input_mode' => 'speed',
            'distance_km' => null,
            'speed_kmh' => 10,
            'duration_seconds' => 1800,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.distance_km', 5)
            ->assertJsonPath('data.speed_kmh', 10)
            ->assertJsonPath('data.pace_seconds_per_km', 360)
            ->assertJsonPath('data.pace', '6:00')
            ->assertJsonPath('data.pulse', 145);

        $this->assertSame(5.0, Workout::query()->firstOrFail()->distance_km);
    }

    public function testSpeedModeAcceptsCalculatedDistanceAtTheUpperBoundaryOnCreateAndUpdate(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->runPayload([
            'input_mode' => 'speed',
            'distance_km' => null,
            'speed_kmh' => 200,
            'duration_seconds' => 18000,
        ]))->assertCreated()
            ->assertJsonPath('data.distance_km', 1000);

        $hashedId = (string) $created->json('data.id');

        $this->putJson('/v1/workouts/' . $hashedId, $this->runPayload([
            'date' => '2025-01-16',
            'input_mode' => 'speed',
            'distance_km' => null,
            'speed_kmh' => 200,
            'duration_seconds' => 18000,
        ]))->assertOk()
            ->assertJsonPath('data.distance_km', 1000)
            ->assertJsonPath('data.date', '2025-01-16');

        $this->assertSame(1000.0, Workout::query()->firstOrFail()->distance_km);
    }

    public function testSpeedModeRejectsCalculatedDistanceAboveTheLimitOnCreateAndUpdate(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->runPayload())
            ->assertCreated();
        $hashedId = (string) $created->json('data.id');

        $overLimit = [
            'input_mode' => 'speed',
            'distance_km' => null,
            'speed_kmh' => 200,
            'duration_seconds' => 86400,
        ];

        $this->postJson('/v1/workouts', $this->runPayload($overLimit))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['speed_kmh']);

        $this->putJson('/v1/workouts/' . $hashedId, $this->runPayload($overLimit))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['speed_kmh']);

        $this->assertSame(1, Workout::query()->count());
        $this->assertDatabaseHas('workouts', [
            'id' => hashids()->decode($hashedId),
            'distance_km' => 5.2,
        ]);
    }

    public function testOtherCardioAcceptsPulseButRejectsIncline(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->cardioPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'cardio')
            ->assertJsonPath('data.distance_km', 12)
            ->assertJsonPath('data.pulse', 135)
            ->assertJsonPath('data.incline_percent', null);

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'incline_percent' => 3,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['incline_percent']);

        $this->assertSame(1, Workout::query()->count());
    }

    public function testTopLevelValidationRejectsInvalidValues(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            ['date' => '2025-02-30'],
            ['type' => 'walking'],
            ['name' => ''],
            ['name' => '   '],
            ['name' => str_repeat('a', 101)],
            ['duration_seconds' => 59],
            ['duration_seconds' => 86401],
            ['duration_seconds' => 3300.5],
            ['duration_seconds' => true],
            ['calories' => -1],
            ['calories' => 1.5],
            ['calories' => 'NaN'],
            ['feeling' => 'great'],
            ['note' => str_repeat('a', 2001)],
        ];

        foreach ($cases as $overrides) {
            $response = $this->postJson('/v1/workouts', $this->strengthPayload($overrides));
            $response->assertUnprocessable();
            $this->assertNotEmpty($response->json('errors'), 'Expected errors for ' . json_encode($overrides));
        }

        $this->postJson('/v1/workouts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name', 'date', 'duration_seconds']);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testNestedStrengthValidationIsStrictPerMode(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            [[
                'name' => 'Упражнение',
                'mode' => 'weighted',
                'sets' => [['reps' => 10]],
            ], 'exercises.0.sets.0.weight_kg'],
            [[
                'name' => 'Упражнение',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 20]],
            ], 'exercises.0.sets.0.reps'],
            [[
                'name' => 'Упражнение',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 20, 'reps' => 10, 'seconds' => 30]],
            ], 'exercises.0.sets.0.seconds'],
            [[
                'name' => 'Упражнение',
                'mode' => 'reps',
                'sets' => [['reps' => 10, 'weight_kg' => 20]],
            ], 'exercises.0.sets.0.weight_kg'],
            [[
                'name' => 'Упражнение',
                'mode' => 'time',
                'sets' => [['seconds' => 30, 'reps' => 10]],
            ], 'exercises.0.sets.0.reps'],
            [[
                'name' => 'Упражнение',
                'mode' => 'time',
                'sets' => [['reps' => 10]],
            ], 'exercises.0.sets.0.seconds'],
            [[
                'name' => '',
                'mode' => 'reps',
                'sets' => [['reps' => 10]],
            ], 'exercises.0.name'],
            [[
                'name' => 'Упражнение',
                'mode' => 'unknown',
                'sets' => [['reps' => 10]],
            ], 'exercises.0.mode'],
            [[
                'name' => 'Упражнение',
                'mode' => 'reps',
                'sets' => [],
            ], 'exercises.0.sets'],
            [[
                'name' => 'Упражнение',
                'mode' => 'reps',
                'sets' => [['reps' => 10, 'unexpected' => 1]],
            ], 'exercises.0.sets.0'],
        ];

        foreach ($cases as [$exercise, $errorKey]) {
            $response = $this->postJson('/v1/workouts', $this->strengthPayload([
                'exercises' => [$exercise],
            ]));

            $response->assertUnprocessable();
            $this->assertArrayHasKey(
                $errorKey,
                $response->json('errors'),
                'Expected ' . $errorKey . ' for ' . json_encode($exercise),
            );
        }

        $this->postJson('/v1/workouts', $this->strengthPayload(['exercises' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exercises']);

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'exercises' => [
                ['name' => 'Жим', 'mode' => 'reps', 'sets' => [['reps' => 10]], 'extra' => true],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['exercises.0']);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testCardioValidationRejectsWrongModesAndMissingMetrics(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            [$this->runPayload(['distance_km' => null]), 'distance_km'],
            [$this->runPayload(['distance_km' => 0]), 'distance_km'],
            [$this->runPayload(['distance_km' => 'NaN']), 'distance_km'],
            [$this->runPayload(['distance_km' => 1.2345]), 'distance_km'],
            [$this->runPayload(['input_mode' => 'speed', 'distance_km' => null]), 'speed_kmh'],
            [$this->runPayload(['input_mode' => 'speed', 'speed_kmh' => 10, 'distance_km' => 5]), 'distance_km'],
            [$this->runPayload(['distance_km' => 5, 'speed_kmh' => 10]), 'speed_kmh'],
            [$this->runPayload(['pulse' => 20]), 'pulse'],
            [$this->runPayload(['pulse' => 250.5]), 'pulse'],
            [$this->runPayload(['incline_percent' => 41]), 'incline_percent'],
            [$this->runPayload(['exercises' => [['name' => 'Жим', 'mode' => 'reps', 'sets' => [['reps' => 1]]]]]), 'exercises'],
            [$this->runPayload(['input_mode' => 'speed', 'speed_kmh' => 0, 'distance_km' => null]), 'speed_kmh'],
        ];

        foreach ($cases as [$payload, $errorKey]) {
            $response = $this->postJson('/v1/workouts', $payload);
            $response->assertUnprocessable();
            $this->assertArrayHasKey($errorKey, $response->json('errors'), 'Expected ' . $errorKey);
        }

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testStrengthRejectsCardioFields(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            ['distance_km' => 5],
            ['speed_kmh' => 10],
            ['input_mode' => 'distance'],
            ['pulse' => 120],
            ['incline_percent' => 2],
        ];

        foreach ($cases as $overrides) {
            $this->postJson('/v1/workouts', $this->strengthPayload($overrides))
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testDiaryReturnsMondayToSundayWeekDaySummariesAndRecentRecords(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'date' => '2025-01-13',
            'duration_seconds' => 1800,
            'calories' => 100,
        ]))->assertCreated();
        $this->postJson('/v1/workouts', $this->runPayload([
            'date' => '2025-01-15',
            'duration_seconds' => 1800,
            'calories' => 200,
            'distance_km' => 4,
        ]))->assertCreated();
        $this->postJson('/v1/workouts', $this->cardioPayload([
            'date' => '2025-01-15',
            'duration_seconds' => 900,
            'calories' => 80,
            'distance_km' => 10,
        ]))->assertCreated();
        $this->postJson('/v1/workouts', $this->runPayload([
            'date' => '2025-01-19',
            'duration_seconds' => 2700,
            'calories' => 300,
            'distance_km' => 7.5,
        ]))->assertCreated();
        $this->postJson('/v1/workouts', $this->runPayload([
            'date' => '2025-01-20',
            'distance_km' => 99,
        ]))->assertCreated();

        $response = $this->getJson('/v1/workouts?date=2025-01-15')->assertOk();

        $response
            ->assertJsonPath('data.date', '2025-01-15')
            ->assertJsonPath('data.week_start', '2025-01-13')
            ->assertJsonPath('data.week_end', '2025-01-19')
            ->assertJsonPath('data.week.count', 4)
            ->assertJsonPath('data.week.minutes', 120)
            ->assertJsonPath('data.week.distance_km', 11.5)
            ->assertJsonCount(7, 'data.days')
            ->assertJsonCount(2, 'data.workouts')
            ->assertJsonPath('data.days.0.date', '2025-01-13')
            ->assertJsonPath('data.days.0.count', 1)
            ->assertJsonPath('data.days.2.date', '2025-01-15')
            ->assertJsonPath('data.days.2.count', 2)
            ->assertJsonPath('data.days.2.distance_km', 4)
            ->assertJsonPath('data.days.6.date', '2025-01-19')
            ->assertJsonCount(4, 'data.recent')
            ->assertJsonPath('data.recent.0.date', '2025-01-20');

        $this->getJson('/v1/workouts?date=2025-01-20')
            ->assertOk()
            ->assertJsonPath('data.week_start', '2025-01-20')
            ->assertJsonPath('data.week.count', 1)
            ->assertJsonPath('data.days.0.date', '2025-01-20');
    }

    public function testEmptyDiaryStillReturnsSevenDaysAndEmptyStates(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->getJson('/v1/workouts?date=2025-01-15')
            ->assertOk()
            ->assertJsonCount(7, 'data.days')
            ->assertJsonCount(0, 'data.workouts')
            ->assertJsonCount(0, 'data.recent')
            ->assertJsonPath('data.week.count', 0)
            ->assertJsonPath('data.week.minutes', 0)
            ->assertJsonPath('data.week.distance_km', 0)
            ->assertJsonPath('data.days.0.has_workouts', false);
    }

    public function testDefaultDateUsesProfileTimezoneNotUtc(): void
    {
        $losAngeles = $this->createCompletedUser(['timezone' => 'America/Los_Angeles']);
        $tokyo = $this->createCompletedUser(['timezone' => 'Asia/Tokyo']);

        Carbon::setTestNow(Carbon::parse('2025-01-16 02:00:00', 'UTC'));

        $this->actingAsUser($losAngeles);
        $this->getJson('/v1/workouts')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/Los_Angeles')
            ->assertJsonPath('data.date', '2025-01-15')
            ->assertJsonPath('data.today', '2025-01-15')
            ->assertJsonPath('data.week_start', '2025-01-13');

        $this->actingAsUser($tokyo);
        $this->getJson('/v1/workouts')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Tokyo')
            ->assertJsonPath('data.date', '2025-01-16')
            ->assertJsonPath('data.today', '2025-01-16')
            ->assertJsonPath('data.week_start', '2025-01-13');
    }

    public function testWeekBoundaryAcrossSundayAndMondayDoesNotLeak(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->runPayload(['date' => '2025-01-19']))->assertCreated();
        $this->postJson('/v1/workouts', $this->runPayload(['date' => '2025-01-20']))->assertCreated();

        $this->getJson('/v1/workouts?date=2025-01-19')
            ->assertOk()
            ->assertJsonPath('data.week_start', '2025-01-13')
            ->assertJsonPath('data.week_end', '2025-01-19')
            ->assertJsonPath('data.week.count', 1)
            ->assertJsonPath('data.week.distance_km', 5.2);

        $this->getJson('/v1/workouts?date=2025-01-20')
            ->assertOk()
            ->assertJsonPath('data.week_start', '2025-01-20')
            ->assertJsonPath('data.week_end', '2025-01-26')
            ->assertJsonPath('data.week.count', 1);
    }

    public function testUpdateReplacesNestedChildrenAndMovesAggregatesBetweenDaysAndWeeks(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->strengthPayload([
            'date' => '2025-01-15',
            'duration_seconds' => 3300,
            'calories' => 320,
        ]))->assertCreated();
        $hashedId = (string) $created->json('data.id');
        $oldId = hashids()->decode($hashedId);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.activity', 320)
            ->assertJsonPath('data.activity.workout_totals.count', 1);

        $updated = $this->putJson('/v1/workouts/' . $hashedId, $this->runPayload([
            'date' => '2025-01-20',
            'duration_seconds' => 1800,
            'calories' => 210,
            'distance_km' => 4.5,
        ]));

        $updated->assertOk()
            ->assertJsonPath('data.id', $hashedId)
            ->assertJsonPath('data.type', 'run')
            ->assertJsonPath('data.date', '2025-01-20')
            ->assertJsonPath('data.calories', 210)
            ->assertJsonPath('data.distance_km', 4.5)
            ->assertJsonPath('data.exercise_count', 0);

        $this->assertSame(0, WorkoutExercise::query()->where('workout_id', $oldId)->count());
        $this->assertSame(0, WorkoutSet::query()->count());
        $this->assertDatabaseHas('workouts', ['id' => $oldId, 'type' => 'run']);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.activity', 0)
            ->assertJsonPath('data.activity.has_entries', false);
        $this->getJson('/v1/workouts?date=2025-01-15')
            ->assertOk()
            ->assertJsonCount(0, 'data.workouts')
            ->assertJsonPath('data.week.count', 0);

        $this->getJson('/v1/dashboard?date=2025-01-20')
            ->assertOk()
            ->assertJsonPath('data.calories.activity', 210)
            ->assertJsonPath('data.steps.minutes', 30);
        $this->getJson('/v1/workouts?date=2025-01-20')
            ->assertOk()
            ->assertJsonCount(1, 'data.workouts')
            ->assertJsonPath('data.week.count', 1)
            ->assertJsonPath('data.week.distance_km', 4.5);
    }

    public function testUpdateAndDeleteAreIsolatedBetweenUsers(): void
    {
        $owner = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($owner);
        $created = $this->postJson('/v1/workouts', $this->strengthPayload())->assertCreated();
        $hashedId = (string) $created->json('data.id');

        $this->actingAsUser($other);
        $this->putJson('/v1/workouts/' . $hashedId, $this->runPayload())
            ->assertNotFound()
            ->assertJsonStructure(['message']);
        $this->deleteJson('/v1/workouts/' . $hashedId)->assertNotFound();

        $this->assertDatabaseHas('workouts', ['id' => hashids()->decode($hashedId), 'type' => 'strength']);

        $this->actingAsUser($owner);
        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.activity', 320);
        $this->deleteJson('/v1/workouts/' . $hashedId)->assertNoContent();
        $this->assertDatabaseMissing('workouts', ['id' => hashids()->decode($hashedId)]);
        $this->assertSame(0, WorkoutExercise::query()->count());
        $this->assertSame(0, WorkoutSet::query()->count());

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.activity', 0)
            ->assertJsonPath('data.activity.has_entries', false);
        $this->getJson('/v1/workouts?date=2025-01-15')
            ->assertOk()
            ->assertJsonCount(0, 'data.workouts')
            ->assertJsonPath('data.week.count', 0);
    }

    public function testUnknownAndMalformedIdsShareOne404(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->getJson('/v1/workouts/not-a-valid-hash')->assertNotFound();
        $this->putJson('/v1/workouts/not-a-valid-hash', $this->runPayload())->assertNotFound();
        $this->deleteJson('/v1/workouts/0')->assertNotFound();
        $this->getJson('/v1/workouts/' . hashids()->encodeOrFail(999999))->assertNotFound();
    }

    public function testDashboardAddsManualActivityAndWorkoutExactlyOnce(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Прогулка',
            'calories' => 200,
            'steps' => 6500,
            'minutes' => 45.5,
            'distance_km' => 4.325,
        ]);

        $this->postJson('/v1/workouts', $this->runPayload([
            'duration_seconds' => 3300,
            'calories' => 320,
            'distance_km' => 3.5,
        ]))->assertCreated();

        $response = $this->getJson('/v1/dashboard?date=2025-01-15')->assertOk();

        $response
            ->assertJsonPath('data.activity.totals.calories', 520)
            ->assertJsonPath('data.activity.totals.minutes', 100.5)
            ->assertJsonPath('data.activity.totals.distance_km', 7.825)
            ->assertJsonPath('data.activity.totals.steps', 6500)
            ->assertJsonPath('data.activity.manual_totals.calories', 200)
            ->assertJsonPath('data.activity.workout_totals.calories', 320)
            ->assertJsonPath('data.activity.workout_totals.count', 1)
            ->assertJsonPath('data.calories.activity', 520)
            ->assertJsonPath('data.calories.target', 2310)
            ->assertJsonPath('data.calories.remaining', 2310)
            ->assertJsonPath('data.steps.minutes', 100.5)
            ->assertJsonPath('data.steps.distance_km', 7.825);

        $this->assertSame(1, ActivityEntry::query()->count());
        $this->assertSame(1, Workout::query()->count());
    }

    public function testDataIsIsolatedBetweenUsersInDiaryAndDashboard(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($other);
        $this->postJson('/v1/workouts', $this->runPayload([
            'name' => 'Чужая пробежка',
            'calories' => 999,
        ]))->assertCreated();

        $this->actingAsUser($user);
        $this->getJson('/v1/workouts?date=2025-01-15')
            ->assertOk()
            ->assertJsonCount(0, 'data.workouts')
            ->assertJsonCount(0, 'data.recent');
        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.activity.has_entries', false)
            ->assertJsonPath('data.calories.activity', 0);
    }

    public function testHistoricalGoalIsNotChangedByWorkoutCalories(): void
    {
        $user = $this->createCompletedUser([], '2025-01-10');
        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->runPayload([
            'date' => '2025-01-12',
            'calories' => 500,
        ]))->assertCreated();

        $this->getJson('/v1/dashboard?date=2025-01-12')
            ->assertOk()
            ->assertJsonPath('data.target.calories', 2320)
            ->assertJsonPath('data.calories.target', 2320)
            ->assertJsonPath('data.calories.activity', 500)
            ->assertJsonPath('data.calories.remaining', 2320);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.target.calories', 2440)
            ->assertJsonPath('data.calories.target', 2440);
    }

    public function testDateMoveInsideSameWeekUpdatesBothDayAndWeekAggregates(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->runPayload([
            'date' => '2025-01-13',
            'duration_seconds' => 1800,
            'distance_km' => 3,
        ]))->assertCreated();
        $hashedId = (string) $created->json('data.id');

        $this->putJson('/v1/workouts/' . $hashedId, $this->runPayload([
            'date' => '2025-01-14',
            'duration_seconds' => 1800,
            'distance_km' => 3,
        ]))->assertOk();

        $this->getJson('/v1/workouts?date=2025-01-14')
            ->assertOk()
            ->assertJsonPath('data.week.count', 1)
            ->assertJsonPath('data.days.0.count', 0)
            ->assertJsonPath('data.days.1.count', 1)
            ->assertJsonPath('data.week.distance_km', 3);

        $this->getJson('/v1/dashboard?date=2025-01-13')
            ->assertOk()
            ->assertJsonPath('data.calories.activity', 0);
        $this->getJson('/v1/dashboard?date=2025-01-14')
            ->assertOk()
            ->assertJsonPath('data.calories.activity', 300);
    }

    public function testCreateRollsBackParentWhenNestedPersistenceFails(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        WorkoutExercise::creating(static function (): void {
            throw new \RuntimeException('forced nested failure');
        });

        try {
            $this->withoutExceptionHandling()->postJson('/v1/workouts', $this->strengthPayload());
            $this->fail('Expected the forced nested failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('forced nested failure', $exception->getMessage());
        } finally {
            WorkoutExercise::flushEventListeners();
        }

        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('workout_exercises', 0);
        $this->assertDatabaseCount('workout_sets', 0);
    }

    public function testUpdateRollsBackParentAndChildrenWhenNestedPersistenceFails(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->strengthPayload())->assertCreated();
        $hashedId = (string) $created->json('data.id');

        WorkoutExercise::creating(static function (): void {
            throw new \RuntimeException('forced nested failure');
        });

        try {
            $this->withoutExceptionHandling()->putJson(
                '/v1/workouts/' . $hashedId,
                $this->strengthPayload(['name' => 'Сломанное обновление']),
            );
            $this->fail('Expected the forced nested failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('forced nested failure', $exception->getMessage());
        } finally {
            WorkoutExercise::flushEventListeners();
        }

        $workout = Workout::query()->firstOrFail();
        $this->assertSame('strength', $workout->type->value);
        $this->assertSame('Верх тела', $workout->name);
        $this->assertSame(2, WorkoutExercise::query()->count());
        $this->assertSame(3, WorkoutSet::query()->count());
    }

    public function testInvalidDateQueryIsRejected(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        foreach (['2025-02-30', '2025-13-01', '15.01.2025', '2025-1-5', 'yesterday'] as $date) {
            $this->getJson('/v1/workouts?date=' . urlencode($date))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['date']);
        }
    }
}
