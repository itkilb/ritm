<?php

namespace App\Containers\AppSection\Workout\Tests\Functional\API;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Tests\ProductApiTestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WorkoutCalorieTest extends ProductApiTestCase
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
            'calories_mode' => 'auto',
            'strength_character' => 'ordinary',
            'feeling' => 'comfortable',
            'note' => null,
            'exercises' => [
                [
                    'name' => 'Приседания',
                    'mode' => 'weighted',
                    'sets' => [['weight_kg' => 60, 'reps' => 10]],
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
            'duration_seconds' => 1800,
            'calories_mode' => 'auto',
            'input_mode' => 'distance',
            'distance_km' => 5,
            'pulse' => 145,
            'run_surface' => 'outdoor_flat',
            'feeling' => 'comfortable',
            'note' => null,
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
            'name' => 'Кардио',
            'duration_seconds' => 1800,
            'calories_mode' => 'auto',
            'activity_kind' => 'stationary_bike',
            'power_watts' => 100,
            'pulse' => 130,
            'feeling' => null,
            'note' => null,
            'exercises' => [],
        ], $overrides);
    }

    public function testGuestCannotPreviewAndCsrfIsRequired(): void
    {
        $this->postJson('/v1/workouts/preview', $this->strengthPayload())
            ->assertUnauthorized();

        $this->forceCsrfValidation();
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->post('/v1/workouts/preview', $this->strengthPayload(), ['Accept' => 'text/html'])
            ->assertStatus(419)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testPreviewCalculatesOnServerWithoutWritingAndReturnsExplanation(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->postJson('/v1/workouts/preview', $this->strengthPayload())
            ->assertOk();

        $response
            ->assertJsonPath('data.calculation.calories_mode', 'auto')
            ->assertJsonPath('data.calculation.calories_status', 'calculated')
            ->assertJsonPath('data.calculation.calories', 150)
            ->assertJsonPath('data.calculation.active_kcal', 150)
            ->assertJsonPath('data.calculation.gross_kcal', 210)
            ->assertJsonPath('data.calculation.met_code', '02054')
            ->assertJsonPath('data.calculation.met_value', 3.5)
            ->assertJsonPath('data.calculation.weight_kg', 80)
            ->assertJsonPath('data.calculation.weight_source', 'weight_entry')
            ->assertJsonPath('data.calculation.catalog_version', '2024/v1')
            ->assertJsonPath('data.calculation.formula_version', 'met-1-v1')
            ->assertJsonPath('data.explanation.category', 'Силовая, обычная нагрузка')
            ->assertJsonPath('data.explanation.duration_seconds', 2700)
            ->assertJsonPath('data.explanation.catalog_url', 'https://pacompendium.com/conditioning-exercise/');

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testRunControlExampleAndSnapshotAreStored(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->runPayload())
            ->assertCreated()
            ->assertJsonPath('data.calories', 332)
            ->assertJsonPath('data.active_kcal', 332)
            ->assertJsonPath('data.gross_kcal', 372)
            ->assertJsonPath('data.met_code', '12050')
            ->assertJsonPath('data.met_value', 9.3)
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.weight_source', 'weight_entry')
            ->assertJsonPath('data.calories_mode', 'auto')
            ->assertJsonPath('data.calories_status', 'calculated')
            ->assertJsonPath('data.run_surface', 'outdoor_flat');

        $id = $created->json('data.id');

        $this->getJson('/v1/workouts/' . $id)
            ->assertOk()
            ->assertJsonPath('data.calories', 332)
            ->assertJsonPath('data.gross_kcal', 372)
            ->assertJsonPath('data.speed_kmh', 10)
            ->assertJsonPath('data.calorie_explanation.active_kcal', 332);

        $this->assertDatabaseHas('workouts', [
            'name' => 'Пробежка',
            'calories' => 332,
            'calories_mode' => 'auto',
            'met_code' => '12050',
        ]);
    }

    public function testManualZeroManualNullAndLegacyDefaultRemainDistinct(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'calories_mode' => 'manual',
            'calories' => 0,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', 0)
            ->assertJsonPath('data.calories_mode', 'manual')
            ->assertJsonPath('data.calories_status', 'manual')
            ->assertJsonPath('data.active_kcal', null);

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'calories_mode' => 'manual',
            'name' => 'Без калорий',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_mode', 'manual')
            ->assertJsonPath('data.calories_status', 'manual');

        $legacy = $this->strengthPayload(['name' => 'Старый клиент']);
        unset($legacy['calories_mode']);

        $this->postJson('/v1/workouts', $legacy)
            ->assertCreated()
            ->assertJsonPath('data.calories', 0)
            ->assertJsonPath('data.calories_mode', 'manual')
            ->assertJsonPath('data.calories_status', 'legacy');

        $legacyWithCalories = $this->strengthPayload(['name' => 'Старый клиент 2', 'calories' => 320]);
        unset($legacyWithCalories['calories_mode']);

        $this->postJson('/v1/workouts', $legacyWithCalories)
            ->assertCreated()
            ->assertJsonPath('data.calories', 320)
            ->assertJsonPath('data.calories_status', 'legacy');
    }

    public function testClientMetCodesAndTrustedCaloriesAreNotAcceptedInAutoMode(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->strengthPayload(['met_code' => '99999']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['met_code']);

        $this->postJson('/v1/workouts', $this->strengthPayload(['met_value' => 99]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['met_value']);

        $this->postJson('/v1/workouts', $this->strengthPayload(['active_kcal' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active_kcal']);

        $this->postJson('/v1/workouts', $this->strengthPayload(['calories' => 100]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['calories']);

        $this->postJson('/v1/workouts', $this->strengthPayload(['weight_kg' => 70]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight_kg']);

        $this->postJson('/v1/workouts', $this->strengthPayload(['strength_character' => 'made_up']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['strength_character']);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testStrengthCharacterSelectsFixedCatalogCodes(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            ['ordinary', '02054', 3.5, 150, 210],
            ['vigorous', '02050', 6, 300, 360],
            ['circuit_moderate', '02035', 5, 240, 300],
        ];

        foreach ($cases as [$character, $code, $met, $active, $gross]) {
            $this->postJson('/v1/workouts', $this->strengthPayload([
                'name' => 'Силовая ' . $character,
                'strength_character' => $character,
            ]))
                ->assertCreated()
                ->assertJsonPath('data.met_code', $code)
                ->assertJsonPath('data.met_value', $met)
                ->assertJsonPath('data.active_kcal', $active)
                ->assertJsonPath('data.gross_kcal', $gross);
        }
    }

    public function testAutoStrengthWithoutCharacterSavesNullMissingInsteadOfGuessing(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->strengthPayload(['strength_character' => null]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'missing')
            ->assertJsonPath('data.calorie_method', 'missing_strength_character')
            ->assertJsonPath('data.met_code', null);
    }

    public function testRunInclineIsUnsupportedUnlessExplicitlyIgnored(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $unsupported = $this->postJson('/v1/workouts', $this->runPayload([
            'name' => 'С горки',
            'incline_percent' => 2.5,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'unsupported')
            ->assertJsonPath('data.calorie_method', 'unsupported_incline')
            ->assertJsonPath('data.incline_ignored', false);

        $this->assertStringContainsString(
            'Расчёт с уклоном пока не поддерживается',
            (string) $unsupported->json('data.calorie_explanation.reason'),
        );

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.target', 2310)
            ->assertJsonPath('data.activity.workout_totals.calories', 0)
            ->assertJsonPath('data.activity.workout_totals.uncalculated_count', 1)
            ->assertJsonPath('data.calories.activity', 0);

        $this->postJson('/v1/workouts', $this->runPayload([
            'name' => 'Без уклона',
            'incline_percent' => 2.5,
            'incline_ignored' => true,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', 332)
            ->assertJsonPath('data.incline_ignored', true)
            ->assertJsonPath('data.gross_kcal', 372);

        $workouts = $this->getJson('/v1/workouts?date=2025-01-15')->json('data.workouts');
        $this->assertIsArray($workouts);
        $byName = collect($workouts)->keyBy('name');
        $this->assertTrue($this->hasWarning($byName['Без уклона']['calorie_warnings'] ?? [], 'Уклон не учтён'));
    }

    public function testRunOutsideSupportedSpeedRangeIsUnsupportedNotClamped(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->runPayload([
            'name' => 'Слишком медленно',
            'distance_km' => 2.4,
            'duration_seconds' => 1800,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'unsupported')
            ->assertJsonPath('data.calorie_method', 'unsupported_run_speed');

        $this->postJson('/v1/workouts', $this->runPayload([
            'name' => 'Слишком быстро',
            'distance_km' => 11.5,
            'duration_seconds' => 1800,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'unsupported');
    }

    public function testCardioStationaryPowerGeneralGapAndUnsupportedPower(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->cardioPayload())
            ->assertCreated()
            ->assertJsonPath('data.distance_km', null)
            ->assertJsonPath('data.speed_kmh', null)
            ->assertJsonPath('data.met_code', '01220')
            ->assertJsonPath('data.met_value', 6)
            ->assertJsonPath('data.active_kcal', 200)
            ->assertJsonPath('data.gross_kcal', 240);

        $general = $this->cardioPayload([
            'name' => 'Общая',
            'power_watts' => null,
            'cardio_intensity' => 'general',
        ]);
        $this->postJson('/v1/workouts', $general)
            ->assertCreated()
            ->assertJsonPath('data.met_code', '01200')
            ->assertJsonPath('data.met_value', 6.8);

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Без мощности',
            'power_watts' => null,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'missing')
            ->assertJsonPath('data.calorie_method', 'missing_bike_power_or_general');

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Слишком мощно',
            'power_watts' => 400,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'unsupported')
            ->assertJsonPath('data.calorie_method', 'unsupported_bike_power');

        $gap = $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Пробел',
            'power_watts' => 40,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.met_code', '01210')
            ->assertJsonPath('data.met_value', 3.5)
            ->assertJsonPath('data.calorie_approximate', true);

        $this->assertTrue($this->hasWarning($gap->json('data.calorie_warnings'), 'пробел'));
    }

    public function testOutdoorBikeAndEllipticalSelection(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Улица',
            'activity_kind' => 'outdoor_bike',
            'power_watts' => null,
            'distance_km' => 6,
            'duration_seconds' => 1800,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.met_code', '01010')
            ->assertJsonPath('data.met_value', 4);

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Улица без скорости',
            'activity_kind' => 'outdoor_bike',
            'power_watts' => null,
            'cardio_intensity' => 'easy',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.met_code', '01015')
            ->assertJsonPath('data.met_value', 4.3);

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Гонка',
            'activity_kind' => 'outdoor_bike',
            'power_watts' => null,
            'distance_km' => 15.5,
            'duration_seconds' => 1800,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'unsupported')
            ->assertJsonPath('data.calorie_method', 'unsupported_bike_speed');

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Эллипс',
            'activity_kind' => 'elliptical',
            'power_watts' => null,
            'cardio_intensity' => 'moderate',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.met_code', '02048')
            ->assertJsonPath('data.met_value', 5);

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Эллипс без категории',
            'activity_kind' => 'elliptical',
            'power_watts' => null,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'missing')
            ->assertJsonPath('data.calorie_method', 'missing_elliptical_intensity');
    }

    public function testUnknownCardioKindSavesUnsupportedWhileManualValueWorks(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Плавание',
            'activity_kind' => 'other',
            'power_watts' => null,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null)
            ->assertJsonPath('data.calories_status', 'unsupported')
            ->assertJsonPath('data.calorie_method', 'unsupported_cardio_kind');

        $this->postJson('/v1/workouts', $this->cardioPayload([
            'name' => 'Плавание вручную',
            'activity_kind' => 'other',
            'power_watts' => null,
            'calories_mode' => 'manual',
            'calories' => 123,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', 123)
            ->assertJsonPath('data.calories_status', 'manual');
    }

    public function testWeightBasisUsesGoalSnapshotFallbackAndExplicitOverride(): void
    {
        $user = $this->createCompletedUser([], '2025-01-10');
        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->strengthPayload(['name' => 'Историческая', 'date' => '2025-01-12']))
            ->assertCreated()
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.weight_source', 'weight_entry');

        $this->postJson('/v1/workouts', $this->strengthPayload(['name' => 'Новая', 'date' => '2025-01-20']))
            ->assertCreated()
            ->assertJsonPath('data.weight_kg', 90)
            ->assertJsonPath('data.weight_source', 'weight_entry');

        $this->postJson('/v1/workouts', $this->strengthPayload(['name' => 'До цели', 'date' => '2025-01-05']))
            ->assertCreated()
            ->assertJsonPath('data.weight_kg', 90)
            ->assertJsonPath('data.weight_source', 'current_profile_fallback')
            ->assertJsonPath('data.calories_status', 'calculated');

        $fallbackWarnings = $this->getJson('/v1/workouts?date=2025-01-05')->json('data.workouts.0.calorie_warnings');
        $this->assertTrue($this->hasWarning($fallbackWarnings, 'Исторической цели'));

        $this->postJson('/v1/workouts', $this->strengthPayload([
            'name' => 'Свой вес',
            'date' => '2025-01-12',
            'calorie_weight_kg' => 70,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.weight_kg', 70)
            ->assertJsonPath('data.weight_source', 'manual_override')
            ->assertJsonPath('data.calories', 131);

        $this->assertSame(90.0, $user->fresh()->profile->weight_kg);
    }

    public function testProfileChangeDoesNotRewriteStoredSnapshotAndEditUsesSavedWeight(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->strengthPayload())
            ->assertCreated()
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.calories', 150);
        $id = $created->json('data.id');

        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user->fresh());

        $this->getJson('/v1/workouts/' . $id)
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.gross_kcal', 210);

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload(['name' => 'Переименована']))
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.weight_source', 'weight_entry');

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload([
            'name' => 'Пересчёт',
            'calories_recalculate' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.calories', 150);

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload([
            'name' => 'Дольше',
            'duration_seconds' => 3600,
        ]))
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.calories', 200)
            ->assertJsonPath('data.gross_kcal', 280);

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload([
            'name' => 'Свой вес',
            'calorie_weight_kg' => 70,
        ]))
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 70)
            ->assertJsonPath('data.weight_source', 'manual_override')
            ->assertJsonPath('data.calories', 131);
    }

    public function testEditIgnoresPulseNoteAndFeelingChangesButReactsToDurationAndDate(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->runPayload())
            ->assertCreated()
            ->assertJsonPath('data.calories', 332);
        $id = $created->json('data.id');

        $this->putJson('/v1/workouts/' . $id, $this->runPayload([
            'name' => 'Другое имя',
            'pulse' => 170,
            'note' => 'Новая заметка',
            'feeling' => 'hard',
        ]))
            ->assertOk()
            ->assertJsonPath('data.calories', 332)
            ->assertJsonPath('data.met_code', '12050');

        $this->putJson('/v1/workouts/' . $id, $this->runPayload([
            'name' => 'Дольше',
            'duration_seconds' => 3600,
            'distance_km' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.calories', 664)
            ->assertJsonPath('data.gross_kcal', 744);

        $this->putJson('/v1/workouts/' . $id, $this->runPayload([
            'name' => 'Другой день',
            'date' => '2025-01-16',
            'duration_seconds' => 3600,
            'distance_km' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.date', '2025-01-16')
            ->assertJsonPath('data.weight_kg', 80)
            ->assertJsonPath('data.calories', 664);
    }

    public function testManualAndLegacyRecordsCanBeExplicitlyRecalculated(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $legacy = $this->strengthPayload(['name' => 'Старая']);
        unset($legacy['calories_mode']);

        $created = $this->postJson('/v1/workouts', $legacy)
            ->assertCreated()
            ->assertJsonPath('data.calories', 0)
            ->assertJsonPath('data.calories_status', 'legacy');
        $id = $created->json('data.id');

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload(['name' => 'Пересчитана']))
            ->assertOk()
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.calories_mode', 'auto')
            ->assertJsonPath('data.weight_kg', 80);

        $manual = $this->postJson('/v1/workouts', $this->strengthPayload([
            'name' => 'Ручная',
            'calories_mode' => 'manual',
            'calories' => 0,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', 0)
            ->assertJsonPath('data.calories_status', 'manual');
        $manualId = $manual->json('data.id');

        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user->fresh());

        $this->getJson('/v1/workouts/' . $manualId)
            ->assertOk()
            ->assertJsonPath('data.calories', 0)
            ->assertJsonPath('data.calories_mode', 'manual');

        $this->putJson('/v1/workouts/' . $manualId, $this->strengthPayload([
            'name' => 'Ручная новая',
            'calories_mode' => 'manual',
            'calories' => 0,
        ]))
            ->assertOk()
            ->assertJsonPath('data.calories', 0)
            ->assertJsonPath('data.calories_status', 'manual');
    }

    public function testPreviewEditUsesSavedWeightAndIsUserIsolated(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/workouts', $this->strengthPayload())
            ->assertCreated();
        $id = $created->json('data.id');

        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user->fresh());

        $this->postJson('/v1/workouts/preview', $this->strengthPayload([
            'workout_id' => $id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.calculation.weight_kg', 80)
            ->assertJsonPath('data.calculation.weight_source', 'weight_entry');

        $other = $this->createCompletedUser();
        $this->actingAsUser($other);

        $this->postJson('/v1/workouts/preview', $this->strengthPayload([
            'workout_id' => $id,
        ]))->assertNotFound();

        $this->postJson('/v1/workouts/preview', $this->strengthPayload([
            'workout_id' => 'not-a-real-id',
        ]))->assertNotFound();
    }

    public function testNullCaloriesDoNotBreakDashboardAndAreNotDuplicatedAsActivityEntries(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts', $this->runPayload([
            'name' => 'С уклоном',
            'incline_percent' => 2.5,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.calories', null);

        $this->postJson('/v1/workouts', $this->runPayload(['name' => 'Обычная']))
            ->assertCreated()
            ->assertJsonPath('data.calories', 332);

        ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Прогулка',
            'calories' => 100,
            'steps' => 3000,
            'minutes' => 20,
            'distance_km' => 2,
        ]);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.target', 2310)
            ->assertJsonPath('data.calories.remaining', 2310)
            ->assertJsonPath('data.activity.manual_totals.calories', 100)
            ->assertJsonPath('data.activity.workout_totals.calories', 332)
            ->assertJsonPath('data.activity.workout_totals.count', 2)
            ->assertJsonPath('data.activity.workout_totals.uncalculated_count', 1)
            ->assertJsonPath('data.activity.totals.calories', 432)
            ->assertJsonPath('data.calories.activity', 432)
            ->assertJsonPath('data.steps.total', 3000);

        $this->assertSame(1, ActivityEntry::query()->where('user_id', $user->getKey())->count());

        $this->getJson('/v1/workouts?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.week.uncalculated_count', 1);
    }

    public function testLegacyRowsReadAsManualAndNullableCaloriesAggregate(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        DB::table('workouts')->insert([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'type' => 'strength',
            'name' => 'Старая запись',
            'duration_seconds' => 2700,
            'calories' => 320,
            'calories_mode' => null,
            'calories_status' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workouts')->insert([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'type' => 'strength',
            'name' => 'Неизвестная запись',
            'duration_seconds' => 2700,
            'calories' => null,
            'calories_mode' => null,
            'calories_status' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/v1/workouts?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.week.calories', 320)
            ->assertJsonPath('data.week.uncalculated_count', 1);

        $workouts = $this->getJson('/v1/workouts?date=2025-01-15')->json('data.workouts');
        $this->assertIsArray($workouts);

        $byName = collect($workouts)->keyBy('name');
        $this->assertSame('manual', $byName['Старая запись']['calories_mode']);
        $this->assertSame('legacy', $byName['Старая запись']['calories_status']);
        $this->assertSame(320, $byName['Старая запись']['calories']);
        $this->assertNull($byName['Неизвестная запись']['calories']);
        $this->assertSame('legacy', $byName['Неизвестная запись']['calories_status']);
    }

    public function testInvalidWeightPowerAndContextReturn422(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        foreach ([34.99, 300.01, 'NaN', '1e9999', -1] as $weight) {
            $payload = $this->strengthPayload(['calorie_weight_kg' => $weight]);

            $this->postJson('/v1/workouts', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['calorie_weight_kg']);

            $this->postJson('/v1/workouts/preview', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['calorie_weight_kg']);
        }

        $this->postJson('/v1/workouts', $this->cardioPayload(['power_watts' => 12.5]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['power_watts']);

        $this->postJson('/v1/workouts', $this->cardioPayload(['activity_kind' => 'swimming']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['activity_kind']);

        $this->postJson('/v1/workouts', $this->cardioPayload(['cardio_intensity' => 'extreme']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cardio_intensity']);

        $this->postJson('/v1/workouts', $this->cardioPayload(['run_surface' => 'treadmill']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['run_surface']);

        $this->postJson('/v1/workouts', $this->strengthPayload(['run_surface' => 'treadmill']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['run_surface']);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function testExactWeightBasisIsStoredReadAndRestoredWithoutRounding(): void
    {
        $user = $this->createCompletedUser(['weight_kg' => 80.265]);
        $this->actingAsUser($user);

        $this->postJson('/v1/workouts/preview', $this->strengthPayload())
            ->assertOk()
            ->assertJsonPath('data.calculation.calories', 150)
            ->assertJsonPath('data.calculation.gross_kcal', 211)
            ->assertJsonPath('data.calculation.weight_kg', 80.265)
            ->assertJsonPath('data.calculation.weight_source', 'weight_entry');

        $created = $this->postJson('/v1/workouts', $this->strengthPayload())
            ->assertCreated()
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.active_kcal', 150)
            ->assertJsonPath('data.gross_kcal', 211)
            ->assertJsonPath('data.weight_kg', 80.265);

        $id = $created->json('data.id');

        $this->getJson('/v1/workouts/' . $id)
            ->assertOk()
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.weight_kg', 80.265)
            ->assertJsonPath('data.calorie_explanation.weight_kg', 80.265);

        $workout = Workout::query()->where('name', 'Силовая')->firstOrFail();
        $this->assertSame(80.265, (float) $workout->weight_kg_exact);
        $this->assertSame(80.265, $workout->weight_kg);

        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user->fresh());

        $this->getJson('/v1/workouts/' . $id)
            ->assertOk()
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.weight_kg', 80.265);

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload([
            'name' => 'Переименована',
            'note' => 'Заметка',
        ]))
            ->assertOk()
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.weight_kg', 80.265)
            ->assertJsonPath('data.weight_source', 'weight_entry');

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload([
            'name' => 'Дольше',
            'duration_seconds' => 3600,
        ]))
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80.265)
            ->assertJsonPath('data.calories', 201)
            ->assertJsonPath('data.gross_kcal', 281);

        $this->putJson('/v1/workouts/' . $id, $this->strengthPayload([
            'name' => 'Восстановлена',
            'calorie_weight_kg' => 80.265,
            'calories_recalculate' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('data.weight_kg', 80.265)
            ->assertJsonPath('data.calories', 150)
            ->assertJsonPath('data.gross_kcal', 211);

        $this->assertSame(
            80.265,
            (float) Workout::query()->whereKey($workout->getKey())->value('weight_kg_exact'),
        );
    }

    public function testPreviewAcceptsAnUnfinishedDraftWhileSaveKeepsStrictDiaryValidation(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $draft = [
            'type' => 'strength',
            'date' => '2025-01-15',
            'duration_seconds' => 2700,
            'calories_mode' => 'auto',
            'strength_character' => 'ordinary',
        ];

        $this->postJson('/v1/workouts/preview', $draft)
            ->assertOk()
            ->assertJsonPath('data.calculation.calories', 150)
            ->assertJsonPath('data.calculation.gross_kcal', 210)
            ->assertJsonPath('data.calculation.weight_kg', 80)
            ->assertJsonPath('data.calculation.weight_source', 'weight_entry');

        $this->assertDatabaseCount('workouts', 0);

        $this->postJson('/v1/workouts', $draft)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'exercises']);

        $runDraft = [
            'type' => 'run',
            'date' => '2025-01-15',
            'duration_seconds' => 1800,
            'calories_mode' => 'auto',
            'input_mode' => 'distance',
        ];

        $this->postJson('/v1/workouts/preview', $runDraft)
            ->assertOk()
            ->assertJsonPath('data.calculation.calories', null)
            ->assertJsonPath('data.calculation.calories_status', 'missing')
            ->assertJsonPath('data.calculation.calorie_method', 'missing_run_distance');

        $this->postJson('/v1/workouts', $runDraft)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['distance_km']);

        $cardioDraft = [
            'type' => 'cardio',
            'date' => '2025-01-15',
            'duration_seconds' => 1800,
            'calories_mode' => 'auto',
        ];

        $this->postJson('/v1/workouts/preview', $cardioDraft)
            ->assertOk()
            ->assertJsonPath('data.calculation.calories', null)
            ->assertJsonPath('data.calculation.calories_status', 'missing')
            ->assertJsonPath('data.calculation.calorie_method', 'missing_activity_kind');

        $this->assertDatabaseCount('workouts', 0);
    }

    /**
     * @param mixed $warnings
     */
    private function hasWarning(mixed $warnings, string $needle): bool
    {
        if (!is_array($warnings)) {
            return false;
        }

        foreach ($warnings as $warning) {
            if (is_string($warning) && str_contains($warning, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function testWithoutMeasurementsGoalSnapshotAndProfileFallbackRemainTheSources(): void
    {
        $user = $this->createCompletedUser([], '2025-01-10');
        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user);

        \App\Containers\AppSection\WeightEntry\Models\WeightEntry::query()
            ->where('user_id', $user->getKey())
            ->delete();

        $this->postJson('/v1/workouts/preview', $this->strengthPayload(['date' => '2025-01-12']))
            ->assertOk()
            ->assertJsonPath('data.calculation.weight_source', 'goal_snapshot')
            ->assertJsonPath('data.calculation.weight_kg', 80);

        $this->postJson('/v1/workouts/preview', $this->strengthPayload(['date' => '2025-01-05']))
            ->assertOk()
            ->assertJsonPath('data.calculation.weight_source', 'current_profile_fallback')
            ->assertJsonPath('data.calculation.weight_kg', 90);
    }
}
