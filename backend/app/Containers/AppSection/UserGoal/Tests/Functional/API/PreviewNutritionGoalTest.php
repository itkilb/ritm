<?php

namespace App\Containers\AppSection\UserGoal\Tests\Functional\API;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Tests\Functional\ApiTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PreviewNutritionGoalTest extends ApiTestCase
{
    public function testGuestCannotPreview(): void
    {
        $this->postJson('/v1/goals/preview', $this->payload())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function testPreviewWithoutCsrfIsJson419EvenWithoutAccept(): void
    {
        $this->forceCsrfValidation();

        $response = $this->post('/v1/goals/preview', $this->payload(), ['Accept' => 'text/html']);

        $response->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
        $this->assertFalse($response->headers->has('Location'));
    }

    public function testPreviewReturnsOnlyServerCalculatedNumbersAndWritesNothing(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/v1/goals/preview', $this->payload([
            'user_id' => $user->getKey(),
            'profile_completed' => true,
            'calories' => 9999,
            'protein' => 999,
            'fat' => 999,
            'carbs' => 999,
            'effective_date' => '1999-01-01',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.calories', 2310)
            ->assertJsonPath('data.protein', 128)
            ->assertJsonPath('data.fat', 64)
            ->assertJsonPath('data.carbs', 306)
            ->assertJsonPath('data.explanation.age', 35)
            ->assertJsonPath('data.explanation.activity_coefficient', 1.55)
            ->assertJsonPath('data.explanation.goal_multiplier', 0.85)
            ->assertJsonPath('data.explanation.protein_basis', 'body_weight')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.effective_date');

        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('user_goals', 0);
        $this->assertFalse($user->fresh()->profile_completed);
        $this->assertNull($user->fresh()->profile);
    }

    public function testPreviewSupportsBothGoalsAndBothGenders(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $this->postJson('/v1/goals/preview', $this->payload([
            'gender' => 'male',
            'goal' => 'lose_weight',
        ]))->assertOk()
            ->assertJsonPath('data.calories', 2310)
            ->assertJsonPath('data.explanation.goal_label', 'Похудение')
            ->assertJsonPath('data.explanation.goal_multiplier', 0.85);

        $this->postJson('/v1/goals/preview', $this->payload([
            'gender' => 'male',
            'goal' => 'gain_muscle',
        ]))->assertOk()
            ->assertJsonPath('data.calories', 2990)
            ->assertJsonPath('data.explanation.goal_label', 'Набор массы')
            ->assertJsonPath('data.explanation.goal_multiplier', 1.1);

        $this->postJson('/v1/goals/preview', $this->payload([
            'gender' => 'female',
            'goal' => 'lose_weight',
        ]))->assertOk()
            ->assertJsonPath('data.calories', 2090)
            ->assertJsonPath('data.explanation.age', 35);
    }

    public function testPreviewWithFatPercentageReturnsLeanMassExplanation(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/v1/goals/preview', $this->payload([
            'fat_percentage' => 25,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.protein', 120)
            ->assertJsonPath('data.explanation.lean_mass_kg', 60)
            ->assertJsonPath('data.explanation.protein_basis', 'lean_mass');

        $this->assertStringContainsString(
            'Процент жира используется только для расчёта белков',
            implode(' ', $response->json('data.explanation.principles')),
        );
    }

    public function testEmptyFatPercentageIsNullableAndDoesNotUseLeanMass(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $this->postJson('/v1/goals/preview', $this->payload([
            'fat_percentage' => '',
        ]))->assertOk()
            ->assertJsonPath('data.fat_percentage', null)
            ->assertJsonPath('data.protein', 128)
            ->assertJsonPath('data.explanation.lean_mass_kg', null)
            ->assertJsonPath('data.explanation.protein_basis', 'body_weight');
    }

    public function testMissingFatPercentageKeyIsTreatedAsNotSpecified(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $payload = $this->payload();
        unset($payload['fat_percentage']);

        $response = $this->postJson('/v1/goals/preview', $payload);

        $response->assertOk()
            ->assertJsonPath('data.fat_percentage', null)
            ->assertJsonPath('data.protein', 128)
            ->assertJsonPath('data.explanation.lean_mass_kg', null)
            ->assertJsonPath('data.explanation.protein_basis', 'body_weight');

        $this->assertArrayHasKey('fat_percentage', $response->json('data'));
    }

    public function testPreviewAcceptsBoundaryParameters(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $this->postJson('/v1/goals/preview', $this->payload([
            'weight_kg' => 35,
            'height_cm' => 230,
            'birth_date' => '2007-01-15',
            'fat_percentage' => 60,
            'gender' => 'male',
            'activity_level' => 'high',
            'goal' => 'gain_muscle',
        ]))->assertOk();

        $this->postJson('/v1/goals/preview', $this->payload([
            'weight_kg' => 300,
            'height_cm' => 120,
            'birth_date' => '1925-01-15',
            'fat_percentage' => 5,
            'gender' => 'male',
            'activity_level' => 'high',
            'goal' => 'gain_muscle',
        ]))->assertOk();

        $this->postJson('/v1/goals/preview', $this->payload(['fat_percentage' => 5]))->assertOk();
        $this->postJson('/v1/goals/preview', $this->payload(['fat_percentage' => 60]))->assertOk();
    }

    public function testPreviewRejectsOutOfRangeAndInvalidValues(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $cases = [
            ['weight_kg' => 34.99],
            ['weight_kg' => 300.01],
            ['height_cm' => 119.99],
            ['height_cm' => 230.01],
            ['fat_percentage' => 4.99],
            ['fat_percentage' => 60.01],
            ['birth_date' => '2025-02-30'],
            ['birth_date' => '2024-02-30'],
            ['birth_date' => '15.01.1990'],
            ['birth_date' => '2008-01-15'],
            ['birth_date' => '1924-01-15'],
            ['gender' => 'unspecified'],
            ['gender' => 'other'],
            ['activity_level' => 'extreme'],
            ['goal' => 'maintain'],
            ['timezone' => 'Mars/Olympus'],
            ['timezone' => '+03:00'],
        ];

        foreach ($cases as $overrides) {
            $response = $this->postJson('/v1/goals/preview', $this->payload($overrides));
            $response->assertUnprocessable();
            $this->assertNotEmpty($response->json('errors'), 'Expected validation errors for ' . json_encode($overrides));
        }
    }

    public function testPreviewReportsMissingRequiredFields(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $this->postJson('/v1/goals/preview', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'weight_kg',
                'height_cm',
                'birth_date',
                'gender',
                'activity_level',
                'goal',
                'timezone',
            ]);
    }

    public function testImpossibleCalculationIsExplicit422AndNotClamped(): void
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
            ->assertJsonPath('errors.calculation.0', $response->json('message'));
        $this->assertStringContainsString('ниже 1200 ккал', (string) $response->json('message'));

        $preview = $this->postJson('/v1/goals/preview', $this->payload([
            'weight_kg' => 35,
            'height_cm' => 120,
            'birth_date' => '1925-01-15',
            'gender' => 'female',
            'activity_level' => 'sedentary',
            'goal' => 'lose_weight',
        ]));
        $preview->assertUnprocessable();
        $this->assertStringContainsString('ниже 1200 ккал', (string) $preview->json('message'));

        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('user_goals', 0);
    }

    public function testGuestCurrentGoalIsUnauthorized(): void
    {
        $this->getJson('/v1/goals/current')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function testCurrentGoalIsNullBeforeTheFirstSavedGoal(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');

        $response = $this->getJson('/v1/goals/current');

        $response->assertOk();
        $this->assertNull($response->json('data'));
    }
}
