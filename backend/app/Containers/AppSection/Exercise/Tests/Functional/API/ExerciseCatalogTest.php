<?php

namespace App\Containers\AppSection\Exercise\Tests\Functional\API;

use App\Containers\AppSection\Exercise\Models\Exercise;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Containers\AppSection\Workout\Models\WorkoutExercise;
use App\Ship\Tests\ProductApiTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ExerciseCatalogTest extends ProductApiTestCase
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
            'name' => 'Силовая',
            'duration_seconds' => 2700,
            'calories' => 150,
            'exercises' => [
                [
                    'name' => 'Жим штанги лёжа',
                    'mode' => 'weighted',
                    'sets' => [['weight_kg' => 60, 'reps' => 8]],
                ],
            ],
        ], $overrides);
    }

    public function testGuestCannotReadOrCreateExercises(): void
    {
        $this->getJson('/v1/exercises')->assertUnauthorized();
        $this->postJson('/v1/exercises', [
            'name' => 'Моё упражнение',
            'muscle_group' => 'Спина',
            'equipment' => 'Гантели',
            'measurement_type' => 'weighted',
        ])->assertUnauthorized();
    }

    public function testCsrfIsRequiredForCreate(): void
    {
        $this->forceCsrfValidation();
        $this->actingAsUser($this->createCompletedUser());

        $this->post('/v1/exercises', [
            'name' => 'Моё упражнение',
            'muscle_group' => 'Спина',
            'equipment' => 'Гантели',
            'measurement_type' => 'weighted',
        ], ['Accept' => 'text/html'])->assertStatus(419);

        $this->assertDatabaseCount('exercises', 38);
    }

    public function testGlobalCatalogIsSeededAndSearchableWithFilters(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->getJson('/v1/exercises')
            ->assertOk()
            ->assertJsonPath('meta.filters.measurement_types', ['weighted', 'reps', 'time', 'bodyweight'])
            ->assertJsonCount(38, 'data');

        $search = $this->getJson('/v1/exercises?search=' . urlencode('жим'))
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($search);

        foreach ($search as $row) {
            $this->assertStringContainsStringIgnoringCase('жим', $row['name']);
        }

        $back = $this->getJson('/v1/exercises?muscle_group=' . urlencode('Спина'))
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($back);

        foreach ($back as $row) {
            $this->assertSame('Спина', $row['muscle_group']);
        }

        $dumbbells = $this->getJson('/v1/exercises?equipment=' . urlencode('Гантели') . '&muscle_group=' . urlencode('Грудь'))
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($dumbbells);

        foreach ($dumbbells as $row) {
            $this->assertSame('Гантели', $row['equipment']);
            $this->assertSame('Грудь', $row['muscle_group']);
        }

        $this->getJson('/v1/exercises?muscle_group=Некорректная')->assertUnprocessable();
    }

    public function testPersonalExerciseIsVisibleOnlyToItsOwner(): void
    {
        $owner = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($owner);

        $created = $this->postJson('/v1/exercises', [
            'name' => '  Тяга к поясу  ',
            'muscle_group' => 'Спина',
            'equipment' => 'Тренажёр',
            'measurement_type' => 'weighted',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Тяга к поясу')
            ->assertJsonPath('data.is_custom', true);

        $id = $created->json('data.id');
        $ownerRows = $this->getJson('/v1/exercises')->assertOk()->json('data');
        $this->assertContains($id, array_column($ownerRows, 'id'));

        $this->actingAsUser($other);
        $otherRows = $this->getJson('/v1/exercises')->assertOk()->json('data');
        $this->assertNotContains($id, array_column($otherRows, 'id'));
    }

    public function testWorkoutExerciseLinksToCatalogAndKeepsSnapshot(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);
        $catalog = Exercise::query()->where('slug', 'bench-press-barbell')->firstOrFail();

        $response = $this->postJson('/v1/workouts', $this->strengthPayload([
            'exercises' => [[
                'exercise_id' => $catalog->getHashedKey(),
                'name' => 'Жим штанги лёжа',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 60, 'reps' => 8]],
            ]],
        ]))->assertCreated()
            ->assertJsonPath('data.exercises.0.exercise_id', $catalog->getHashedKey())
            ->assertJsonPath('data.exercises.0.name', 'Жим штанги лёжа')
            ->assertJsonPath('data.exercises.0.mode', 'weighted');

        $workoutExercise = WorkoutExercise::query()->firstOrFail();
        $this->assertSame($catalog->getKey(), $workoutExercise->exercise_id);
        $this->assertSame('Жим штанги лёжа', $workoutExercise->name);

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'date' => '2025-01-16',
            'exercises' => [[
                'exercise_id' => $catalog->getHashedKey(),
                'name' => 'Жим штанги лёжа',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 62.5, 'reps' => 6]],
            ]],
        ]))->assertCreated()
            ->assertJsonPath('data.exercises.0.exercise_id', $catalog->getHashedKey());
    }

    public function testLegacyWorkoutExerciseStaysUnlinkedAndCanBeLinkedManually(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);
        $catalog = Exercise::query()->where('slug', 'bench-press-barbell')->firstOrFail();

        $created = $this->postJson('/v1/workouts', $this->strengthPayload())
            ->assertCreated()
            ->assertJsonPath('data.exercises.0.exercise_id', null);

        $id = $created->json('data.id');

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload([
            'exercises' => [[
                'exercise_id' => $catalog->getHashedKey(),
                'name' => 'Жим штанги лёжа',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 60, 'reps' => 8]],
            ]],
        ]))->assertOk()
            ->assertJsonPath('data.exercises.0.exercise_id', $catalog->getHashedKey())
            ->assertJsonPath('data.exercises.0.sets.0.weight_kg', 60)
            ->assertJsonPath('data.exercises.0.sets.0.reps', 8);
    }

    public function testForeignUnknownAndMismatchedExercisesAreRejectedWithoutDataLoss(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($other);
        $foreign = $this->postJson('/v1/exercises', [
            'name' => 'Чужое упражнение',
            'muscle_group' => 'Руки',
            'equipment' => 'Гантели',
            'measurement_type' => 'weighted',
        ])->assertCreated()->json('data.id');

        $this->actingAsUser($user);
        $plank = Exercise::query()->where('slug', 'plank')->firstOrFail();

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'exercises' => [[
                'exercise_id' => $foreign,
                'name' => 'Чужое упражнение',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 60, 'reps' => 8]],
            ]],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['exercises.0.exercise_id']);

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'exercises' => [[
                'exercise_id' => 'unknown-id',
                'name' => 'Неизвестное',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 60, 'reps' => 8]],
            ]],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['exercises.0.exercise_id']);

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'exercises' => [[
                'exercise_id' => $plank->getHashedKey(),
                'name' => 'Планка',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 60, 'reps' => 8]],
            ]],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['exercises.0.mode']);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testBodyweightAndWeightedModesAreLosslesslyCompatible(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);
        $pushUps = Exercise::query()->where('slug', 'push-ups')->firstOrFail();

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'exercises' => [[
                'exercise_id' => $pushUps->getHashedKey(),
                'name' => 'Отжимания',
                'mode' => 'bodyweight',
                'sets' => [['weight_kg' => 10, 'reps' => 12]],
            ]],
        ]))->assertCreated()
            ->assertJsonPath('data.exercises.0.mode', 'bodyweight')
            ->assertJsonPath('data.exercises.0.sets.0.weight_kg', 10)
            ->assertJsonPath('data.exercises.0.sets.0.reps', 12);

        $this->assertSame(1, Workout::query()->count());
    }
}
