<?php

namespace App\Containers\AppSection\WeightEntry\Tests\Functional\API;

use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Ship\Tests\ProductApiTestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WeightEntryTest extends ProductApiTestCase
{
    public function testGuestCannotUseWeightApi(): void
    {
        $this->getJson('/v1/weight-entries')->assertUnauthorized();
        $this->postJson('/v1/weight-entries', ['date' => '2025-01-15', 'weight_kg' => 80])->assertUnauthorized();
        $this->putJson('/v1/weight-entries/abc', ['date' => '2025-01-15', 'weight_kg' => 80])->assertUnauthorized();
        $this->deleteJson('/v1/weight-entries/abc')->assertUnauthorized();
        $this->putJson('/v1/me/target-weight', ['target_weight_kg' => 75])->assertUnauthorized();
    }

    public function testCsrfIsRequiredForWeightMutations(): void
    {
        $this->forceCsrfValidation();
        $this->actingAsUser($this->createCompletedUser());

        $this->post('/v1/weight-entries', [
            'date' => '2025-01-14',
            'weight_kg' => 80,
        ], ['Accept' => 'text/html'])->assertStatus(419);

        $this->put('/v1/me/target-weight', [
            'target_weight_kg' => 75,
        ], ['Accept' => 'text/html'])->assertStatus(419);
    }

    public function testProfileConfirmationCreatesOneMeasurementForToday(): void
    {
        $user = $this->createCompletedUser(['weight_kg' => 80.265]);

        $entries = WeightEntry::query()->where('user_id', $user->getKey())->get();
        $this->assertCount(1, $entries);
        $this->assertSame('2025-01-15', $entries[0]->date->format('Y-m-d'));
        $this->assertSame(80.265, (float) $entries[0]->weight_kg);
    }

    public function testCreatesUpdatesListsAndDeletesOwnMeasurementWithProfileSync(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-10',
            'weight_kg' => 78.125,
        ])->assertCreated()
            ->assertJsonPath('data.date', '2025-01-10')
            ->assertJsonPath('data.weight_kg', 78.125);

        $id = $created->json('data.id');

        $this->assertSame(80.0, $user->fresh()->profile->weight_kg);

        $second = $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-15',
            'weight_kg' => 81.5,
        ]);
        $second->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $this->putJson('/v1/weight-entries/' . $id, [
            'date' => '2025-01-14',
            'weight_kg' => 79.25,
        ])->assertOk()
            ->assertJsonPath('data.date', '2025-01-14')
            ->assertJsonPath('data.weight_kg', 79.25);

        $this->assertSame(80.0, $user->fresh()->profile->weight_kg);

        $listed = $this->getJson('/v1/weight-entries?from=2025-01-10&to=2025-01-15')
            ->assertOk()
            ->assertJsonPath('meta.today', '2025-01-15')
            ->assertJsonPath('meta.limit', 500)
            ->assertJsonPath('meta.returned', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.has_more', false)
            ->json('data');
        $this->assertCount(2, $listed);
        $this->assertSame(['2025-01-14', '2025-01-15'], array_column($listed, 'date'));

        $todayId = $listed[1]['id'];
        $this->deleteJson('/v1/weight-entries/' . $todayId)->assertNoContent();
        $this->assertSame(79.25, $user->fresh()->profile->weight_kg);

        $this->deleteJson('/v1/weight-entries/' . $id)->assertNoContent();
        $this->assertSame(79.25, $user->fresh()->profile->weight_kg);
        $this->assertSame(0, WeightEntry::query()->count());
    }

    public function testDuplicateFutureAndOutOfRangeValuesAreRejected(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-15',
            'weight_kg' => 81,
        ])->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $this->assertSame(1, WeightEntry::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(80.0, $user->fresh()->profile->weight_kg);
        $this->assertSame(
            80.0,
            (float) WeightEntry::query()->whereDate('date', '2025-01-15')->firstOrFail()->weight_kg,
        );

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-16',
            'weight_kg' => 81,
        ])->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-14',
            'weight_kg' => 34.999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['weight_kg']);

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-14',
            'weight_kg' => 300.001,
        ])->assertUnprocessable()->assertJsonValidationErrors(['weight_kg']);

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-14',
            'weight_kg' => 80.1234,
        ])->assertUnprocessable()->assertJsonValidationErrors(['weight_kg']);

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-02-30',
            'weight_kg' => 80,
        ])->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-14',
            'weight_kg' => 35,
        ])->assertCreated();

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-13',
            'weight_kg' => 300,
        ])->assertCreated();

        $jan14 = WeightEntry::query()->whereDate('date', '2025-01-14')->firstOrFail();
        $this->putJson('/v1/weight-entries/' . $jan14->getHashedKey(), [
            'date' => '2025-01-15',
            'weight_kg' => 70,
        ])->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $this->assertSame(35.0, (float) $jan14->fresh()->weight_kg);
        $this->assertSame(80.0, (float) WeightEntry::query()->whereDate('date', '2025-01-15')->firstOrFail()->weight_kg);
    }

    public function testFutureIsEvaluatedInTheProfileTimezone(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        Carbon::setTestNow(Carbon::parse('2025-01-15 22:00:00 UTC'));

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-16',
            'weight_kg' => 80,
        ])->assertCreated();

        $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-17',
            'weight_kg' => 80,
        ])->assertUnprocessable()->assertJsonValidationErrors(['date']);
    }

    public function testForeignUnknownAndMalformedIdsAreIndistinguishable(): void
    {
        $owner = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($owner);

        $id = $this->postJson('/v1/weight-entries', [
            'date' => '2025-01-14',
            'weight_kg' => 79,
        ])->assertCreated()->json('data.id');

        $this->actingAsUser($other);

        $this->putJson('/v1/weight-entries/' . $id, [
            'date' => '2025-01-14',
            'weight_kg' => 70,
        ])->assertNotFound();

        $this->deleteJson('/v1/weight-entries/' . $id)->assertNotFound();

        foreach (['unknown-id', 'not-a-real-id'] as $bad) {
            $this->putJson('/v1/weight-entries/' . $bad, [
                'date' => '2025-01-14',
                'weight_kg' => 70,
            ])->assertNotFound();
            $this->deleteJson('/v1/weight-entries/' . $bad)->assertNotFound();
        }

        $this->assertSame(79.0, (float) WeightEntry::query()->whereDate('date', '2025-01-14')->firstOrFail()->weight_kg);
    }

    public function testProfileEditsDoNotOverwriteTodaysManualMeasurementUnlessWeightChanges(): void
    {
        $user = $this->createCompletedUser(['weight_kg' => 80]);
        $today = WeightEntry::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', '2025-01-15')
            ->firstOrFail();

        $today->update(['weight_kg' => 79.4]);
        UserProfile::query()->where('user_id', $user->getKey())->update([
            'weight_kg' => 80,
            'weight_kg_exact' => 80,
        ]);

        $this->confirmProfile($user, ['weight_kg' => 80, 'height_cm' => 185], '2025-01-15');
        $this->assertSame(79.4, (float) $today->fresh()->weight_kg);
        $this->assertSame(1, WeightEntry::query()->where('user_id', $user->getKey())->count());

        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->assertSame(90.0, (float) $today->fresh()->weight_kg);
        $this->assertSame(1, WeightEntry::query()->where('user_id', $user->getKey())->count());
    }

    public function testTargetWeightCanBeSetAndClearedWithoutTouchingNutritionGoals(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);
        $goalId = $user->goals()->firstOrFail()->getKey();

        $this->putJson('/v1/me/target-weight', ['target_weight_kg' => 74.125])
            ->assertOk()
            ->assertJsonPath('data.target_weight_kg', 74.125);

        $this->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.target_weight_kg', 74.125);

        $this->putJson('/v1/me/target-weight', ['target_weight_kg' => 20])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_weight_kg']);

        $this->putJson('/v1/me/target-weight', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_weight_kg']);

        $this->putJson('/v1/me/target-weight', ['target_weight_kg' => null])
            ->assertOk()
            ->assertJsonPath('data.target_weight_kg', null);

        $this->assertSame($goalId, $user->goals()->firstOrFail()->getKey());
        $this->assertSame(80.0, $user->fresh()->profile->weight_kg);
    }
    public function testWeightJournalNeverRewritesGoalsOrSavedWorkoutCalorieSnapshots(): void
    {
        $user = $this->createCompletedUser(['weight_kg' => 80]);
        $this->actingAsUser($user);

        $workoutId = $this->postJson('/v1/workouts', [
            'type' => 'strength',
            'date' => '2025-01-15',
            'name' => 'Силовая',
            'duration_seconds' => 2700,
            'calories_mode' => 'auto',
            'strength_character' => 'ordinary',
            'exercises' => [[
                'name' => 'Приседания',
                'mode' => 'weighted',
                'sets' => [['weight_kg' => 60, 'reps' => 10]],
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.weight_kg', 80)
            ->json('data.id');

        $goal = $user->goals()->firstOrFail();

        $todayEntry = WeightEntry::query()
            ->where('user_id', $user->getKey())
            ->whereDate('date', '2025-01-15')
            ->firstOrFail();

        $this->putJson('/v1/weight-entries/' . $todayEntry->getHashedKey(), [
            'date' => '2025-01-15',
            'weight_kg' => 91,
        ])->assertOk();

        $this->assertSame(91.0, $user->fresh()->profile->weight_kg);

        $this->getJson('/v1/workouts/' . $workoutId)
            ->assertOk()
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.calories_mode', 'auto');

        $this->assertSame($goal->getKey(), $user->goals()->firstOrFail()->getKey());
    }

}
