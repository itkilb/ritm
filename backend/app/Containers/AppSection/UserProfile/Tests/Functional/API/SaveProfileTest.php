<?php

namespace App\Containers\AppSection\UserProfile\Tests\Functional\API;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserGoal\Services\NutritionCalculator;
use App\Containers\AppSection\UserGoal\Tasks\ResolveUserGoalForDateTask;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Containers\AppSection\UserProfile\Tests\Functional\ApiTestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class SaveProfileTest extends ApiTestCase
{
    public function testGuestCannotSaveProfile(): void
    {
        $this->putJson('/v1/me/profile', $this->payload())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function testSaveWithoutCsrfIsJson419EvenWithoutAccept(): void
    {
        $this->forceCsrfValidation();

        $response = $this->put(
            '/v1/me/profile',
            $this->payload(),
            ['Accept' => 'text/html'],
        );

        $response->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
        $this->assertFalse($response->headers->has('Location'));
    }

    public function testSaveUsesServerCalculationPersistsProfileAndMarksCompletion(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $response = $this->putJson('/v1/me/profile', $this->payload([
            'user_id' => $other->getKey(),
            'profile_completed' => false,
            'calories' => 9999,
            'protein' => 999,
            'fat' => 999,
            'carbs' => 999,
            'effective_date' => '1999-01-01',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.profile_completed', true)
            ->assertJsonPath('data.user.profile.weight_kg', 80)
            ->assertJsonPath('data.goal.calories', 2310)
            ->assertJsonPath('data.goal.protein', 128)
            ->assertJsonPath('data.goal.fat', 64)
            ->assertJsonPath('data.goal.carbs', 306)
            ->assertJsonPath('data.goal.effective_date', '2025-01-15')
            ->assertJsonPath('data.goal.timezone', 'Europe/Moscow')
            ->assertJsonPath('data.goal.explanation.age', 35)
            ->assertJsonPath('data.goal.explanation.tdee', 2720.25)
            ->assertJsonPath('data.goal.explanation.goal_multiplier', 0.85);

        $profile = UserProfile::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->assertSame($user->getKey(), $profile->user_id);
        $this->assertEqualsWithDelta(80.0, $profile->weight_kg, 0.0001);
        $this->assertNull($profile->fat_percentage);

        $goal = UserGoal::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->assertSame(2310, $goal->calories);
        $this->assertSame('2025-01-15', $goal->effective_date->format('Y-m-d'));
        $this->assertNull(UserProfile::query()->where('user_id', $other->getKey())->first());
        $this->assertSame(0, UserGoal::query()->where('user_id', $other->getKey())->count());
        $this->assertFalse($other->fresh()->profile_completed);

        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web');

        $me = $this->getJson('/v1/me');
        $me->assertOk()
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.profile.weight_kg', 80)
            ->assertJsonPath('data.profile.birth_date', '1990-01-15')
            ->assertJsonPath('data.profile.gender', 'male')
            ->assertJsonPath('data.profile.activity_level', 'moderate')
            ->assertJsonPath('data.profile.goal', 'lose_weight')
            ->assertJsonPath('data.profile.timezone', 'Europe/Moscow');

        $current = $this->getJson('/v1/goals/current');
        $current->assertOk()
            ->assertJsonPath('data.id', $goal->getHashedKey())
            ->assertJsonPath('data.calories', 2310)
            ->assertJsonPath('data.effective_date', '2025-01-15')
            ->assertJsonPath('data.explanation.activity_label', 'Тренировки 3–5 раз в неделю');
    }

    public function testSaveWithoutFatPercentageKeyUsesBodyWeightAndReadsBack(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $payload = $this->payload();
        unset($payload['fat_percentage']);

        $save = $this->putJson('/v1/me/profile', $payload);

        $save->assertOk()
            ->assertJsonPath('data.goal.fat_percentage', null)
            ->assertJsonPath('data.goal.protein', 128)
            ->assertJsonPath('data.goal.explanation.protein_basis', 'body_weight')
            ->assertJsonPath('data.goal.explanation.lean_mass_kg', null);

        $this->assertArrayHasKey('fat_percentage', $save->json('data.goal'));

        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web');

        $this->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.fat_percentage', null)
            ->assertJsonPath('data.profile.weight_kg', 80);

        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.fat_percentage', null)
            ->assertJsonPath('data.protein', 128)
            ->assertJsonPath('data.explanation.protein_basis', 'body_weight');

        $this->assertSame(1, UserGoal::query()->where('user_id', $user->getKey())->count());
    }

    public function testFractionalWeightWithoutFatKeepsProteinAfterSaveAndReload(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $payload = $this->payload(['weight_kg' => 80.3125]);

        $preview = $this->postJson('/v1/goals/preview', $payload);
        $preview->assertOk()
            ->assertJsonPath('data.weight_kg', 80.3125)
            ->assertJsonPath('data.protein', 129);

        $this->putJson('/v1/me/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.goal.weight_kg', 80.3125)
            ->assertJsonPath('data.goal.protein', 129);

        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web');

        $this->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile.weight_kg', 80.3125);

        $current = $this->getJson('/v1/goals/current');
        $current->assertOk()
            ->assertJsonPath('data.weight_kg', 80.3125)
            ->assertJsonPath('data.protein', 129);

        $roundTripPayload = array_intersect_key($current->json('data'), array_flip([
            'weight_kg',
            'height_cm',
            'birth_date',
            'fat_percentage',
            'gender',
            'activity_level',
            'goal',
            'timezone',
        ]));

        $this->postJson('/v1/goals/preview', $roundTripPayload)
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80.3125)
            ->assertJsonPath('data.protein', 129);
    }

    public function testFractionalInputsAndCalculationSnapshotsRoundTripThroughPreviewAndSave(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $payload = $this->payload([
            'weight_kg' => 80.3125,
            'height_cm' => 180.1234,
            'fat_percentage' => 25.4321,
        ]);

        $preview = $this->postJson('/v1/goals/preview', $payload);
        $preview->assertOk();
        $previewData = $preview->json('data');

        $save = $this->putJson('/v1/me/profile', $payload);
        $save->assertOk();
        $savedGoal = $save->json('data.goal');

        foreach (['weight_kg', 'height_cm', 'fat_percentage'] as $field) {
            $this->assertEqualsWithDelta($payload[$field], (float) $savedGoal[$field], 1e-12, $field);
        }

        foreach (['calories', 'protein', 'fat', 'carbs'] as $field) {
            $this->assertSame($previewData[$field], $savedGoal[$field], $field);
        }

        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web');

        $me = $this->getJson('/v1/me');
        $me->assertOk();
        foreach (['weight_kg', 'height_cm', 'fat_percentage'] as $field) {
            $this->assertEqualsWithDelta($payload[$field], (float) $me->json('data.profile.' . $field), 1e-12, $field);
        }

        $current = $this->getJson('/v1/goals/current');
        $current->assertOk();
        $currentData = $current->json('data');

        foreach (['weight_kg', 'height_cm', 'fat_percentage'] as $field) {
            $this->assertEqualsWithDelta($payload[$field], (float) $currentData[$field], 1e-12, $field);
        }

        foreach (['calories', 'protein', 'fat', 'carbs'] as $field) {
            $this->assertSame($previewData[$field], $currentData[$field], $field);
        }

        $goal = UserGoal::query()->where('user_id', $user->getKey())->latest('id')->firstOrFail();
        $calculation = app(NutritionCalculator::class)->calculate($payload, '2025-01-15');
        $this->assertEqualsWithDelta($calculation->bmr, $goal->bmr, 1e-12);
        $this->assertEqualsWithDelta($calculation->tdee, $goal->tdee, 1e-12);
        $this->assertEqualsWithDelta((float) $calculation->leanMassKg, (float) $goal->lean_mass_kg, 1e-12);
        $this->assertSame($previewData['explanation']['bmr'], $currentData['explanation']['bmr']);
        $this->assertSame($previewData['explanation']['tdee'], $currentData['explanation']['tdee']);
        $this->assertSame($previewData['explanation']['lean_mass_kg'], $currentData['explanation']['lean_mass_kg']);

        $roundTripPayload = array_intersect_key($currentData, array_flip([
            'weight_kg',
            'height_cm',
            'birth_date',
            'fat_percentage',
            'gender',
            'activity_level',
            'goal',
            'timezone',
        ]));

        $roundTrip = $this->postJson('/v1/goals/preview', $roundTripPayload);
        $roundTrip->assertOk();
        $roundTripData = $roundTrip->json('data');

        foreach (['weight_kg', 'height_cm', 'fat_percentage'] as $field) {
            $this->assertEqualsWithDelta($payload[$field], (float) $roundTripData[$field], 1e-12, $field);
        }

        foreach (['calories', 'protein', 'fat', 'carbs'] as $field) {
            $this->assertSame($previewData[$field], $roundTripData[$field], $field);
        }
    }

    public function testLatestConfirmationWinsWhenTimezoneMovesBackAcrossDateBoundary(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        Carbon::setTestNow(Carbon::parse('2025-01-15 12:30:00', 'UTC'));

        $first = $this->putJson('/v1/me/profile', $this->payload([
            'weight_kg' => 80,
            'timezone' => 'Pacific/Kiritimati',
        ]));
        $first->assertOk()->assertJsonPath('data.goal.effective_date', '2025-01-16');
        $firstId = $first->json('data.goal.id');

        $second = $this->putJson('/v1/me/profile', $this->payload([
            'weight_kg' => 90,
            'timezone' => 'America/Los_Angeles',
        ]));
        $second->assertOk()->assertJsonPath('data.goal.effective_date', '2025-01-15');
        $secondId = $second->json('data.goal.id');
        $this->assertNotSame($firstId, $secondId);

        Carbon::setTestNow(Carbon::parse('2025-01-16 12:30:00', 'UTC'));

        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.id', $secondId)
            ->assertJsonPath('data.weight_kg', 90)
            ->assertJsonPath('data.effective_date', '2025-01-15');

        /** @var ResolveUserGoalForDateTask $resolve */
        $resolve = app(ResolveUserGoalForDateTask::class);

        $this->assertNull($resolve->run($user, '2025-01-14'));
        foreach (['2025-01-15', '2025-01-16', '2025-01-17'] as $date) {
            $resolved = $resolve->run($user, $date);
            $this->assertNotNull($resolved);
            $this->assertSame($secondId, $resolved->getHashedKey(), $date);
            $this->assertEqualsWithDelta(90.0, $resolved->weight_kg, 1e-12, $date);
        }

        $this->assertSame(2, UserGoal::query()->where('user_id', $user->getKey())->count());
        $storedFirst = UserGoal::query()->where('user_id', $user->getKey())->orderBy('id')->firstOrFail();
        $this->assertEqualsWithDelta(80.0, $storedFirst->weight_kg, 1e-12);
        $this->assertSame('2025-01-16', $storedFirst->effective_date->format('Y-m-d'));

        Carbon::setTestNow(Carbon::parse('2025-01-20 12:30:00', 'UTC'));
        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.id', $secondId);
    }

    public function testTimezoneMoveForwardKeepsHistoryBeforeNewEffectiveDate(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        Carbon::setTestNow(Carbon::parse('2025-01-15 12:30:00', 'UTC'));

        $first = $this->putJson('/v1/me/profile', $this->payload([
            'weight_kg' => 80,
            'timezone' => 'America/Los_Angeles',
        ]));
        $first->assertOk()->assertJsonPath('data.goal.effective_date', '2025-01-15');
        $firstId = $first->json('data.goal.id');

        $second = $this->putJson('/v1/me/profile', $this->payload([
            'weight_kg' => 90,
            'timezone' => 'Pacific/Kiritimati',
        ]));
        $second->assertOk()->assertJsonPath('data.goal.effective_date', '2025-01-16');
        $secondId = $second->json('data.goal.id');

        Carbon::setTestNow(Carbon::parse('2025-01-16 12:30:00', 'UTC'));

        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.id', $secondId);

        /** @var ResolveUserGoalForDateTask $resolve */
        $resolve = app(ResolveUserGoalForDateTask::class);

        $this->assertNull($resolve->run($user, '2025-01-14'));

        $before = $resolve->run($user, '2025-01-15');
        $this->assertNotNull($before);
        $this->assertSame($firstId, $before->getHashedKey());
        $this->assertEqualsWithDelta(80.0, $before->weight_kg, 1e-12);

        foreach (['2025-01-16', '2025-01-17', '2025-02-01'] as $date) {
            $resolved = $resolve->run($user, $date);
            $this->assertNotNull($resolved);
            $this->assertSame($secondId, $resolved->getHashedKey(), $date);
            $this->assertEqualsWithDelta(90.0, $resolved->weight_kg, 1e-12, $date);
        }

        Carbon::setTestNow(Carbon::parse('2025-01-17 12:30:00', 'UTC'));
        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.id', $secondId);
    }

    public function testSaveHasTheSameValidationContractAsPreview(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $this->putJson('/v1/me/profile', $this->payload(['weight_kg' => 34.99]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight_kg']);

        $this->putJson('/v1/me/profile', $this->payload(['birth_date' => '2025-02-30']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date']);

        $this->putJson('/v1/me/profile', $this->payload(['timezone' => 'Not/AZone']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone']);

        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('user_goals', 0);
        $this->assertFalse($user->fresh()->profile_completed);
    }

    public function testFailedCalculationLeavesNoPartialProfileGoalOrCompletion(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $response = $this->putJson('/v1/me/profile', $this->payload([
            'weight_kg' => 35,
            'height_cm' => 120,
            'birth_date' => '1925-01-15',
            'gender' => 'female',
            'activity_level' => 'sedentary',
            'goal' => 'lose_weight',
        ]));

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['calculation']]);
        $this->assertStringContainsString('ниже 1200 ккал', (string) $response->json('errors.calculation.0'));

        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('user_goals', 0);
        $this->assertFalse($user->fresh()->profile_completed);
    }

    public function testPreviewNeverChangesSavedProfileOrCompletionAndBackDoesNotSave(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $this->putJson('/v1/me/profile', $this->payload())->assertOk();

        $this->postJson('/v1/goals/preview', $this->payload(['weight_kg' => 95]))
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 95);

        $this->assertEqualsWithDelta(
            80.0,
            UserProfile::query()->where('user_id', $user->getKey())->firstOrFail()->weight_kg,
            0.0001,
        );
        $this->assertSame(1, UserGoal::query()->where('user_id', $user->getKey())->count());
        $this->getJson('/v1/me')->assertJsonPath('data.profile.weight_kg', 80);

        $this->putJson('/v1/me/profile', $this->payload(['weight_kg' => 95]))
            ->assertOk()
            ->assertJsonPath('data.user.profile.weight_kg', 95);

        $this->assertSame(2, UserGoal::query()->where('user_id', $user->getKey())->count());
        $this->getJson('/v1/goals/current')->assertJsonPath('data.weight_kg', 95);
    }

    public function testMultipleSavesSameDayKeepHistoryAndPastDaysStayImmutable(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $this->putJson('/v1/me/profile', $this->payload(['weight_kg' => 80]))->assertOk();
        $this->putJson('/v1/me/profile', $this->payload(['weight_kg' => 90]))->assertOk();

        $this->assertSame(2, UserGoal::query()->where('user_id', $user->getKey())->count());
        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 90);

        /** @var ResolveUserGoalForDateTask $resolve */
        $resolve = app(ResolveUserGoalForDateTask::class);
        $this->assertNull($resolve->run($user, '2025-01-14'));
        $this->assertEqualsWithDelta(90.0, $resolve->run($user, '2025-01-15')->weight_kg, 0.0001);

        Carbon::setTestNow(Carbon::parse('2025-01-16 12:00:00', 'UTC'));
        $this->putJson('/v1/me/profile', $this->payload(['weight_kg' => 100]))->assertOk();

        $this->assertSame(3, UserGoal::query()->where('user_id', $user->getKey())->count());
        $this->getJson('/v1/goals/current')->assertJsonPath('data.weight_kg', 100);

        $this->assertEqualsWithDelta(90.0, $resolve->run($user, '2025-01-15')->weight_kg, 0.0001);
        $this->assertEqualsWithDelta(100.0, $resolve->run($user, '2025-01-16')->weight_kg, 0.0001);
        $this->assertEqualsWithDelta(100.0, $resolve->run($user, '2025-01-17')->weight_kg, 0.0001);

        $first = UserGoal::query()->where('user_id', $user->getKey())->orderBy('id')->firstOrFail();
        $this->assertEqualsWithDelta(80.0, $first->weight_kg, 0.0001);
        $this->assertSame(2310, $first->calories);
    }

    public function testTimezoneBoundaryDeterminesEffectiveDateAndCurrentGoal(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        Carbon::setTestNow(Carbon::parse('2025-06-15 02:00:00', 'UTC'));

        $this->putJson('/v1/me/profile', $this->payload([
            'birth_date' => '1990-01-15',
            'timezone' => 'America/Los_Angeles',
        ]))->assertOk()
            ->assertJsonPath('data.goal.effective_date', '2025-06-14');

        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.effective_date', '2025-06-14');

        Carbon::setTestNow(Carbon::parse('2025-06-15 08:00:00', 'UTC'));

        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.effective_date', '2025-06-14');
    }

    public function testBirthdayIsEvaluatedInTheUsersTimezone(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');
        $payload = $this->payload([
            'birth_date' => '2007-06-15',
            'timezone' => 'America/Los_Angeles',
        ]);

        Carbon::setTestNow(Carbon::parse('2025-06-15 02:00:00', 'UTC'));
        $this->putJson('/v1/me/profile', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date']);

        Carbon::setTestNow(Carbon::parse('2025-06-15 08:00:00', 'UTC'));
        $this->putJson('/v1/me/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.goal.explanation.age', 18)
            ->assertJsonPath('data.goal.effective_date', '2025-06-15');
    }

    public function testUsersCannotReadOrWriteEachOthersProfileAndGoal(): void
    {
        $first = User::factory()->createOne(['email' => 'first@example.com']);
        $second = User::factory()->createOne(['email' => 'second@example.com']);

        $this->actingAs($first, 'web');
        $this->putJson('/v1/me/profile', $this->payload(['weight_kg' => 80]))->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($second, 'web');
        $this->putJson('/v1/me/profile', $this->payload(['weight_kg' => 95]))->assertOk();

        $firstProfile = UserProfile::query()->where('user_id', $first->getKey())->firstOrFail();
        $secondProfile = UserProfile::query()->where('user_id', $second->getKey())->firstOrFail();
        $this->assertEqualsWithDelta(80.0, $firstProfile->weight_kg, 0.0001);
        $this->assertEqualsWithDelta(95.0, $secondProfile->weight_kg, 0.0001);
        $this->assertSame(1, UserGoal::query()->where('user_id', $first->getKey())->count());
        $this->assertSame(1, UserGoal::query()->where('user_id', $second->getKey())->count());

        $this->app['auth']->forgetGuards();
        $this->actingAs($first, 'web');

        $this->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'first@example.com')
            ->assertJsonPath('data.profile.weight_kg', 80);

        $this->getJson('/v1/goals/current')
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80);
    }
}
