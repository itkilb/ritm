<?php

namespace App\Containers\AppSection\FoodEntry\Tests\Functional\API;

use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Tests\ProductApiTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class FoodEntryTest extends ProductApiTestCase
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
            'name' => 'Овсянка с ягодами',
            'calories' => 340.5,
            'protein' => 12.25,
            'fat' => 8.5,
            'carbs' => 55.75,
        ], $overrides);
    }

    public function testGuestCannotCreateFoodEntry(): void
    {
        $this->postJson('/v1/food-entries', $this->payload())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('food_entries', 0);
    }

    public function testCreateWithoutCsrfIsJson419RegardlessOfAccept(): void
    {
        $this->forceCsrfValidation();
        $user = User::factory()->createOne();
        $this->actingAsUser($user);

        $response = $this->post('/v1/food-entries', $this->payload(), ['Accept' => 'text/html']);

        $response->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertJsonContentType($response);
        $this->assertDatabaseCount('food_entries', 0);
    }

    public function testValidationIsJson422EvenWithHtmlAccept(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->post('/v1/food-entries', $this->payload([
            'date' => '2025-02-30',
            'name' => '   ',
        ]), ['Accept' => 'text/html']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date', 'name']);
        $this->assertJsonContentType($response);
    }

    public function testCreateTrimsNameAndIgnoresClientOwnershipAndInternals(): void
    {
        $user = $this->createCompletedUser();
        $other = User::factory()->createOne();
        $this->actingAsUser($user);

        $response = $this->postJson('/v1/food-entries', $this->payload([
            'name' => '  Овсянка  ',
            'user_id' => $other->getKey(),
            'id' => 999,
            'created_at' => '1999-01-01 00:00:00',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Овсянка')
            ->assertJsonPath('data.date', '2025-01-15')
            ->assertJsonPath('data.calories', 340.5)
            ->assertJsonPath('data.protein', 12.25)
            ->assertJsonPath('data.fat', 8.5)
            ->assertJsonPath('data.carbs', 55.75);

        $entry = FoodEntry::query()->firstOrFail();
        $this->assertSame($user->getKey(), $entry->user_id);
        $this->assertSame(0, FoodEntry::query()->where('user_id', $other->getKey())->count());
        $this->assertSame('2025-01-15', $entry->date->format('Y-m-d'));
    }

    public function testInvalidPayloadIsRejectedWithFieldErrors(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            ['date' => '2025-02-30'],
            ['date' => '2025-13-01'],
            ['date' => '2025-00-10'],
            ['date' => '15.01.2025'],
            ['date' => '2025-1-5'],
            ['date' => ''],
            ['name' => ''],
            ['name' => '   '],
            ['name' => str_repeat('a', 121)],
            ['calories' => -0.01],
            ['calories' => 'NaN'],
            ['calories' => 'Infinity'],
            ['calories' => 'not-a-number'],
            ['calories' => 1.234],
            ['protein' => -1],
            ['fat' => 'NaN'],
            ['carbs' => 2.345],
        ];

        foreach ($cases as $overrides) {
            $response = $this->postJson('/v1/food-entries', $this->payload($overrides));
            $response->assertUnprocessable();
            $this->assertNotEmpty($response->json('errors'), 'Expected errors for ' . json_encode($overrides));
        }

        $this->postJson('/v1/food-entries', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'name', 'calories', 'protein', 'fat', 'carbs']);

        $this->assertDatabaseCount('food_entries', 0);
    }

    public function testOwnerCanDeleteOwnEntry(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();

        $this->actingAsUser($other);
        $foreign = $this->postJson('/v1/food-entries', $this->payload(['name' => 'Чужая запись']))
            ->assertCreated();

        $this->actingAsUser($user);
        $created = $this->postJson('/v1/food-entries', $this->payload())->assertCreated();
        $id = (string) $created->json('data.id');

        $this->deleteJson("/v1/food-entries/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('food_entries', ['id' => hashids()->decode($id)]);
        $this->assertDatabaseHas('food_entries', ['id' => hashids()->decode((string) $foreign->json('data.id'))]);
    }

    public function testForeignUnknownAndMalformedIdsShareOne404(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($other);
        $foreign = $this->postJson('/v1/food-entries', $this->payload(['name' => 'Секрет']))->assertCreated();
        $foreignId = (string) $foreign->json('data.id');

        $this->actingAsUser($user);

        $foreignResponse = $this->deleteJson("/v1/food-entries/{$foreignId}");
        $foreignResponse->assertNotFound()->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('Секрет', (string) $foreignResponse->getContent());

        $unknownId = hashids()->encodeOrFail(999999);
        $this->deleteJson("/v1/food-entries/{$unknownId}")->assertNotFound();

        $this->deleteJson('/v1/food-entries/not-a-valid-hash')->assertNotFound();
        $this->deleteJson('/v1/food-entries/0')->assertNotFound();

        $this->assertDatabaseHas('food_entries', ['id' => hashids()->decode((string) $foreign->json('data.id'))]);
        $this->assertSame(0, FoodEntry::query()->where('user_id', $user->getKey())->count());
    }

    public function testGuestCannotDelete(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);
        $created = $this->postJson('/v1/food-entries', $this->payload())->assertCreated();

        $this->app['auth']->forgetGuards();

        $hashedId = (string) $created->json('data.id');
        $this->deleteJson('/v1/food-entries/' . $hashedId)
            ->assertUnauthorized();

        $this->assertDatabaseHas('food_entries', ['id' => hashids()->decode($hashedId)]);
    }
}
