<?php

namespace App\Containers\AppSection\ActivityEntry\Tests\Functional\API;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Tests\ProductApiTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ActivityEntryTest extends ProductApiTestCase
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
            'name' => 'Прогулка',
            'calories' => 180.5,
            'steps' => 6500,
            'minutes' => 45.5,
            'distance_km' => 4.325,
        ], $overrides);
    }

    public function testGuestCannotCreateActivity(): void
    {
        $this->postJson('/v1/activity-entries', $this->payload())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('activity_entries', 0);
    }

    public function testCreateWithoutCsrfIsJson419RegardlessOfAccept(): void
    {
        $this->forceCsrfValidation();
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->post('/v1/activity-entries', $this->payload(), ['Accept' => 'text/html']);

        $response->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertJsonContentType($response);
    }

    public function testCreateStoresAllMetricsAndIgnoresOwnershipInput(): void
    {
        $user = $this->createCompletedUser();
        $other = User::factory()->createOne();
        $this->actingAsUser($user);

        $response = $this->postJson('/v1/activity-entries', $this->payload([
            'name' => '  Прогулка  ',
            'user_id' => $other->getKey(),
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Прогулка')
            ->assertJsonPath('data.date', '2025-01-15')
            ->assertJsonPath('data.calories', 180.5)
            ->assertJsonPath('data.steps', 6500)
            ->assertJsonPath('data.minutes', 45.5)
            ->assertJsonPath('data.distance_km', 4.325);

        $entry = ActivityEntry::query()->firstOrFail();
        $this->assertSame($user->getKey(), $entry->user_id);
        $this->assertSame(6500, $entry->steps);
        $this->assertSame(0, ActivityEntry::query()->where('user_id', $other->getKey())->count());
    }

    public function testInvalidActivityPayloadIsRejected(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            ['date' => '2025-02-30'],
            ['name' => ''],
            ['name' => '   '],
            ['calories' => -1],
            ['calories' => 1.234],
            ['steps' => -1],
            ['steps' => 1.5],
            ['steps' => true],
            ['steps' => false],
            ['steps' => null],
            ['steps' => []],
            ['steps' => [6500]],
            ['steps' => 'NaN'],
            ['steps' => '1.0'],
            ['steps' => 1000001],
            ['minutes' => -0.01],
            ['minutes' => 1.234],
            ['distance_km' => -0.001],
            ['distance_km' => 1.2345],
            ['distance_km' => 100000],
            ['distance_km' => 'Infinity'],
        ];

        foreach ($cases as $overrides) {
            $response = $this->postJson('/v1/activity-entries', $this->payload($overrides));
            $response->assertUnprocessable();
            $this->assertNotEmpty($response->json('errors'), 'Expected errors for ' . json_encode($overrides));
        }

        $this->postJson('/v1/activity-entries', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'name', 'calories', 'steps', 'minutes', 'distance_km']);

        $this->assertDatabaseCount('activity_entries', 0);
    }

    public function testDistanceBoundaryMatchesStoragePrecision(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/activity-entries', $this->payload([
            'distance_km' => 99999.999,
        ]))->assertCreated()
            ->assertJsonPath('data.distance_km', 99999.999);
    }

    public function testZeroMetricsAreAccepted(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/activity-entries', $this->payload([
            'calories' => 0,
            'steps' => 0,
            'minutes' => 0,
            'distance_km' => 0,
        ]))->assertCreated()
            ->assertJsonPath('data.calories', 0)
            ->assertJsonPath('data.steps', 0);
    }

    public function testIntegerNumericStringStepsAreAccepted(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/activity-entries', $this->payload([
            'steps' => '6500',
        ]))->assertCreated()
            ->assertJsonPath('data.steps', 6500);
    }

    public function testRejectedNonIntegerStepsDoNotCreateRowsEvenWithoutJsonAccept(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->json(
            'POST',
            '/v1/activity-entries',
            $this->payload(['steps' => true]),
            ['Accept' => 'text/html'],
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['steps']);
        $this->assertJsonContentType($response);

        $rawFloat = $this->call(
            'POST',
            '/v1/activity-entries',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'Accept' => 'text/html'],
            json_encode(
                $this->payload(['steps' => 1.0]),
                JSON_PRESERVE_ZERO_FRACTION,
            ),
        );
        $rawFloat->assertUnprocessable()->assertJsonValidationErrors(['steps']);
        $this->assertJsonContentType($rawFloat);
        $this->assertDatabaseCount('activity_entries', 0);

        $this->postJson('/v1/activity-entries', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.steps', 6500);
        $this->assertDatabaseCount('activity_entries', 1);
    }
}
