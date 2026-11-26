<?php

namespace App\Containers\AppSection\FoodEntry\Tests\Functional\API;

use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Tests\ProductApiTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class FoodMealTypeTest extends ProductApiTestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2025-01-15',
            'name' => 'Приём пищи',
            'calories' => 100,
            'protein' => 10,
            'fat' => 5,
            'carbs' => 20,
        ], $overrides);
    }

    public function testCreateStoresFourCategoriesAndLegacyNull(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $index => $mealType) {
            $this->postJson('/v1/food-entries', $this->payload([
                'name' => 'Категория ' . $mealType,
                'meal_type' => $mealType,
                'calories' => 100 + $index,
            ]))->assertCreated()->assertJsonPath('data.meal_type', $mealType);
        }

        $legacy = $this->postJson('/v1/food-entries', $this->payload([
            'name' => 'Старая запись',
        ]));

        $legacy->assertCreated()->assertJsonPath('data.meal_type', null);

        $this->assertSame(4, FoodEntry::query()->whereNotNull('meal_type')->count());
        $this->assertSame(1, FoodEntry::query()->whereNull('meal_type')->count());
        $this->assertSame(
            ['breakfast', 'lunch', 'dinner', 'snack'],
            FoodEntry::query()->whereNotNull('meal_type')->orderBy('id')->pluck('meal_type')->all(),
        );
    }

    public function testInvalidMealTypeShapesAreRejected(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            ['meal_type' => 'brunch'],
            ['meal_type' => 'Завтрак'],
            ['meal_type' => ['breakfast']],
            ['meal_type' => true],
            ['meal_type' => false],
            ['meal_type' => 1],
            ['meal_type' => ''],
        ];

        foreach ($cases as $overrides) {
            $response = $this->postJson('/v1/food-entries', $this->payload($overrides));
            $response->assertUnprocessable()->assertJsonValidationErrors(['meal_type']);
        }

        $this->postJson('/v1/food-entries', $this->payload(['meal_type' => null]))
            ->assertCreated()
            ->assertJsonPath('data.meal_type', null);

        $this->assertDatabaseCount('food_entries', 1);
    }

    public function testGuestCannotChangeMealType(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);
        $created = $this->postJson('/v1/food-entries', $this->payload())->assertCreated();

        $this->app['auth']->forgetGuards();

        $this->patchJson('/v1/food-entries/' . $created->json('data.id') . '/meal-type', [
            'meal_type' => 'lunch',
        ])->assertUnauthorized();

        $this->assertSame(null, FoodEntry::query()->firstOrFail()->meal_type);
    }

    public function testChangeWithoutCsrfIsJson419(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);
        $created = $this->postJson('/v1/food-entries', $this->payload())->assertCreated();

        $this->forceCsrfValidation();

        $response = $this->patch(
            '/v1/food-entries/' . $created->json('data.id') . '/meal-type',
            ['meal_type' => 'lunch'],
            ['Accept' => 'text/html'],
        );

        $response->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertJsonContentType($response);
        $this->assertSame(null, FoodEntry::query()->firstOrFail()->meal_type);
    }

    public function testOwnerAssignsLegacyAndMovesBetweenGroupsWithoutChangingNutritionOrDate(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/food-entries', $this->payload([
            'name' => 'Овсянка',
            'calories' => 340.5,
            'protein' => 12.25,
            'fat' => 8.5,
            'carbs' => 55.75,
        ]))->assertCreated();

        $id = (string) $created->json('data.id');

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.food.uncategorized.count', 1)
            ->assertJsonPath('data.food.meal_groups.0.count', 0);

        $assign = $this->patchJson("/v1/food-entries/{$id}/meal-type", [
            'meal_type' => 'breakfast',
        ]);

        $assign->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.meal_type', 'breakfast')
            ->assertJsonPath('data.date', '2025-01-15')
            ->assertJsonPath('data.name', 'Овсянка')
            ->assertJsonPath('data.calories', 340.5)
            ->assertJsonPath('data.protein', 12.25)
            ->assertJsonPath('data.fat', 8.5)
            ->assertJsonPath('data.carbs', 55.75);

        $this->patchJson("/v1/food-entries/{$id}/meal-type", [
            'meal_type' => 'dinner',
        ])->assertOk()->assertJsonPath('data.meal_type', 'dinner');

        $entry = FoodEntry::query()->firstOrFail();
        $this->assertSame('dinner', $entry->meal_type);
        $this->assertSame('2025-01-15', $entry->date->format('Y-m-d'));
        $this->assertSame(340.5, (float) $entry->calories);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.food.uncategorized.count', 0)
            ->assertJsonPath('data.food.meal_groups.0.meal_type', 'breakfast')
            ->assertJsonPath('data.food.meal_groups.0.count', 0)
            ->assertJsonPath('data.food.meal_groups.2.meal_type', 'dinner')
            ->assertJsonPath('data.food.meal_groups.2.count', 1)
            ->assertJsonPath('data.food.meal_groups.2.entries.0.id', $id)
            ->assertJsonPath('data.food.entries.0.meal_type', 'dinner')
            ->assertJsonPath('data.food.totals.calories', 340.5)
            ->assertJsonPath('data.calories.eaten', 340.5);
    }

    public function testPatchCannotChangeForbiddenFields(): void
    {
        $user = $this->createCompletedUser();
        $other = User::factory()->createOne();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/food-entries', $this->payload([
            'name' => 'Исходное имя',
            'calories' => 250.25,
            'protein' => 11.5,
            'fat' => 7.25,
            'carbs' => 33.5,
        ]))->assertCreated();

        $id = (string) $created->json('data.id');

        $this->patchJson("/v1/food-entries/{$id}/meal-type", [
            'meal_type' => 'snack',
            'name' => 'Взломанное имя',
            'date' => '2025-02-01',
            'calories' => 9999,
            'protein' => 999,
            'fat' => 999,
            'carbs' => 999,
            'user_id' => $other->getKey(),
            'id' => 123456,
        ])->assertOk()->assertJsonPath('data.meal_type', 'snack');

        $entry = FoodEntry::query()->firstOrFail();
        $this->assertSame($user->getKey(), $entry->user_id);
        $this->assertSame('Исходное имя', $entry->name);
        $this->assertSame('2025-01-15', $entry->date->format('Y-m-d'));
        $this->assertSame(250.25, (float) $entry->calories);
        $this->assertSame(11.5, (float) $entry->protein);
        $this->assertSame(7.25, (float) $entry->fat);
        $this->assertSame(33.5, (float) $entry->carbs);
        $this->assertSame('snack', $entry->meal_type);
        $this->assertSame(0, FoodEntry::query()->where('user_id', $other->getKey())->count());
    }

    public function testPatchRejectsInvalidValuesAndForeignUnknownMalformedIds(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($other);
        $foreign = $this->postJson('/v1/food-entries', $this->payload([
            'name' => 'Чужая',
            'meal_type' => 'lunch',
        ]))->assertCreated();

        $this->actingAsUser($user);
        $own = $this->postJson('/v1/food-entries', $this->payload(['name' => 'Своя']))->assertCreated();
        $ownId = (string) $own->json('data.id');

        foreach ([
            ['meal_type' => 'brunch'],
            ['meal_type' => ['lunch']],
            ['meal_type' => true],
            ['meal_type' => null],
            [],
        ] as $body) {
            $this->patchJson("/v1/food-entries/{$ownId}/meal-type", $body)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['meal_type']);
        }

        $foreignId = (string) $foreign->json('data.id');
        $this->patchJson("/v1/food-entries/{$foreignId}/meal-type", ['meal_type' => 'dinner'])
            ->assertNotFound();
        $this->patchJson('/v1/food-entries/' . hashids()->encodeOrFail(999999) . '/meal-type', [
            'meal_type' => 'dinner',
        ])->assertNotFound();
        $this->patchJson('/v1/food-entries/not-a-valid-hash/meal-type', ['meal_type' => 'dinner'])
            ->assertNotFound();
        $this->patchJson('/v1/food-entries/0/meal-type', ['meal_type' => 'dinner'])
            ->assertNotFound();

        $this->assertSame(null, FoodEntry::query()->where('id', hashids()->decode($ownId))->firstOrFail()->meal_type);
        $this->assertSame('lunch', FoodEntry::query()->where('id', hashids()->decode($foreignId))->firstOrFail()->meal_type);
    }

    public function testDashboardMealGroupsPartitionEveryEntryExactlyOnce(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $rows = [
            ['breakfast', 'Завтрак', 100.25, 10.5, 5.25, 20.5],
            ['lunch', 'Обед', 200.5, 20.25, 10.5, 30.25],
            ['dinner', 'Ужин', 300.75, 30.5, 15.25, 40.5],
            ['snack', 'Перекус 1', 400.1, 40.25, 20.5, 50.25],
            ['snack', 'Перекус 2', 30.05, 3.15, 1.15, 5.15],
            [null, 'Без категории', 50.15, 5.15, 2.15, 7.15],
        ];

        $createdIds = [];

        foreach ($rows as [$mealType, $name, $calories, $protein, $fat, $carbs]) {
            $payload = $this->payload([
                'name' => $name,
                'calories' => $calories,
                'protein' => $protein,
                'fat' => $fat,
                'carbs' => $carbs,
            ]);

            if (null !== $mealType) {
                $payload['meal_type'] = $mealType;
            }

            $createdIds[] = (string) $this->postJson('/v1/food-entries', $payload)
                ->assertCreated()
                ->json('data.id');
        }

        $response = $this->getJson('/v1/dashboard?date=2025-01-15')->assertOk();

        $response
            ->assertJsonCount(4, 'data.food.meal_groups')
            ->assertJsonCount(6, 'data.food.entries')
            ->assertJsonPath('data.food.meal_groups.0.meal_type', 'breakfast')
            ->assertJsonPath('data.food.meal_groups.1.meal_type', 'lunch')
            ->assertJsonPath('data.food.meal_groups.2.meal_type', 'dinner')
            ->assertJsonPath('data.food.meal_groups.3.meal_type', 'snack')
            ->assertJsonPath('data.food.meal_groups.0.count', 1)
            ->assertJsonPath('data.food.meal_groups.1.count', 1)
            ->assertJsonPath('data.food.meal_groups.2.count', 1)
            ->assertJsonPath('data.food.meal_groups.3.count', 2)
            ->assertJsonPath('data.food.uncategorized.meal_type', null)
            ->assertJsonPath('data.food.uncategorized.count', 1)
            ->assertJsonPath('data.food.uncategorized.entries.0.name', 'Без категории')
            ->assertJsonPath('data.food.uncategorized.entries.0.meal_type', null)
            ->assertJsonPath('data.food.totals.calories', 1081.8)
            ->assertJsonPath('data.calories.eaten', 1081.8);

        $groups = $response->json('data.food.meal_groups');
        $uncategorized = $response->json('data.food.uncategorized');
        $allIds = [];

        foreach ([...$groups, $uncategorized] as $group) {
            $this->assertSame(
                $group['count'],
                count($group['entries']),
                'Group count must match its entries',
            );

            foreach ($group['entries'] as $entry) {
                $allIds[] = $entry['id'];
            }
        }

        $flatIds = array_column($response->json('data.food.entries'), 'id');
        sort($allIds);
        sort($flatIds);

        $this->assertSame($flatIds, $allIds);
        $this->assertSame(count($flatIds), count(array_unique($allIds)));

        $sums = ['calories' => 0.0, 'protein' => 0.0, 'fat' => 0.0, 'carbs' => 0.0];

        foreach ([...$groups, $uncategorized] as $group) {
            foreach ($sums as $key => $value) {
                $sums[$key] = $value + (float) $group['totals'][$key];
            }
        }

        foreach ($sums as $key => $value) {
            $this->assertEqualsWithDelta(
                (float) $response->json("data.food.totals.{$key}"),
                $value,
                0.0001,
                "Group totals must add up to the flat total for {$key}",
            );
        }

        $this->assertSame(6, count($createdIds));
        $expectedGroupTotals = [
            ['calories' => 100.25, 'protein' => 10.5, 'fat' => 5.25, 'carbs' => 20.5],
            ['calories' => 200.5, 'protein' => 20.25, 'fat' => 10.5, 'carbs' => 30.25],
            ['calories' => 300.75, 'protein' => 30.5, 'fat' => 15.25, 'carbs' => 40.5],
            ['calories' => 430.15, 'protein' => 43.4, 'fat' => 21.65, 'carbs' => 55.4],
        ];

        foreach ($expectedGroupTotals as $index => $expected) {
            foreach ($expected as $key => $value) {
                $this->assertEqualsWithDelta($value, (float) $groups[$index]['totals'][$key], 0.0001);
            }
        }

        $this->assertEqualsWithDelta(50.15, (float) $uncategorized['totals']['calories'], 0.0001);
    }

    public function testEmptyDayStillReturnsFourZeroGroupsAndNoLegacySection(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->getJson('/v1/dashboard?date=2025-01-15')->assertOk();

        $response
            ->assertJsonPath('data.food.has_entries', false)
            ->assertJsonPath('data.food.totals.calories', 0)
            ->assertJsonCount(4, 'data.food.meal_groups')
            ->assertJsonPath('data.food.meal_groups.0.count', 0)
            ->assertJsonPath('data.food.meal_groups.1.count', 0)
            ->assertJsonPath('data.food.meal_groups.2.count', 0)
            ->assertJsonPath('data.food.meal_groups.3.count', 0)
            ->assertJsonPath('data.food.meal_groups.0.totals.calories', 0)
            ->assertJsonPath('data.food.uncategorized.count', 0)
            ->assertJsonPath('data.food.uncategorized.entries', []);

        $this->assertSame([], $response->json('data.food.entries'));
    }

    public function testWeeklyStatsCountsCategorizedEntriesOnce(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $entries = [
            ['breakfast', 100.25],
            ['lunch', 200.5],
            ['dinner', 300.75],
            ['snack', 400.1],
        ];

        foreach ($entries as [$mealType, $calories]) {
            $this->postJson('/v1/food-entries', $this->payload([
                'meal_type' => $mealType,
                'calories' => $calories,
            ]))->assertCreated();
        }

        $expected = 1001.6;

        $weekly = $this->getJson('/v1/weekly-stats?start=2025-01-13')->assertOk();
        $weekly
            ->assertJsonPath('data.days.2.eaten', $expected)
            ->assertJsonPath('data.eaten_total', $expected);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.food.totals.calories', $expected)
            ->assertJsonPath('data.calories.eaten', $expected);
    }
}
