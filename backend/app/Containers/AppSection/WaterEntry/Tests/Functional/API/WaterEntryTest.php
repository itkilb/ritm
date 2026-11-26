<?php

namespace App\Containers\AppSection\WaterEntry\Tests\Functional\API;

use App\Containers\AppSection\WaterEntry\Models\WaterEntry;
use App\Ship\Tests\ProductApiTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WaterEntryTest extends ProductApiTestCase
{
    public function testGuestCannotAddWater(): void
    {
        $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 250])
            ->assertUnauthorized();

        $this->assertDatabaseCount('water_entries', 0);
    }

    public function testAddWaterWithoutCsrfIsJson419RegardlessOfAccept(): void
    {
        $this->forceCsrfValidation();
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->post('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 250], ['Accept' => 'text/html']);

        $response->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertJsonContentType($response);
    }

    public function testOneDailyCounterAccumulatesAndIsNotCappedAtTwoLitres(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        for ($glass = 1; $glass <= 9; ++$glass) {
            $expected = $glass * 250;
            $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 250])
                ->assertOk()
                ->assertJsonPath('data.date', '2025-01-15')
                ->assertJsonPath('data.amount_ml', $expected)
                ->assertJsonPath('data.target_ml', 2000);
        }

        $this->assertSame(1, WaterEntry::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(2250, (int) WaterEntry::query()->firstOrFail()->amount_ml);
    }

    public function testCustomIncrementAddsToExistingDay(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 1000])
            ->assertOk()
            ->assertJsonPath('data.amount_ml', 1000);

        $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 1500])
            ->assertOk()
            ->assertJsonPath('data.amount_ml', 2500);

        $this->assertSame(1, WaterEntry::query()->count());
    }

    public function testDaysAndUsersHaveIndependentCounters(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 250])
            ->assertOk()->assertJsonPath('data.amount_ml', 250);
        $this->postJson('/v1/water-entries', ['date' => '2025-01-16', 'amount_ml' => 500])
            ->assertOk()->assertJsonPath('data.amount_ml', 500);

        $this->actingAsUser($other);
        $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 750])
            ->assertOk()->assertJsonPath('data.amount_ml', 750);

        $this->assertSame(3, WaterEntry::query()->count());
    }

    public function testInvalidWaterPayloadIsRejected(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $cases = [
            ['amount_ml' => 0],
            ['amount_ml' => -250],
            ['amount_ml' => 250.5],
            ['amount_ml' => true],
            ['amount_ml' => false],
            ['amount_ml' => null],
            ['amount_ml' => []],
            ['amount_ml' => [250]],
            ['amount_ml' => 'NaN'],
            ['amount_ml' => '250.5'],
            ['amount_ml' => '1.0'],
            ['amount_ml' => 1000001],
            ['date' => '2025-02-30'],
            ['date' => '2025-1-15'],
            ['date' => ''],
            ['date' => '2025-13-01'],
        ];

        foreach ($cases as $overrides) {
            $this->postJson('/v1/water-entries', array_merge(
                ['date' => '2025-01-15', 'amount_ml' => 250],
                $overrides,
            ))->assertUnprocessable();
        }

        $this->postJson('/v1/water-entries', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'amount_ml']);

        $this->assertDatabaseCount('water_entries', 0);
    }

    public function testIntegerNumericStringIsAcceptedAsWaterIncrement(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/water-entries', [
            'date' => '2025-01-15',
            'amount_ml' => '250',
        ])->assertOk()
            ->assertJsonPath('data.amount_ml', 250);
    }

    public function testRejectedNonIntegerPayloadIsJson422WithoutAcceptAndDoesNotIncrement(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 250])
            ->assertOk()
            ->assertJsonPath('data.amount_ml', 250);

        $response = $this->json(
            'POST',
            '/v1/water-entries',
            ['date' => '2025-01-15', 'amount_ml' => true],
            ['Accept' => 'text/html'],
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['amount_ml']);
        $this->assertJsonContentType($response);

        $rawFloat = $this->call(
            'POST',
            '/v1/water-entries',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'Accept' => 'text/html'],
            json_encode(
                ['date' => '2025-01-15', 'amount_ml' => 1.0],
                JSON_PRESERVE_ZERO_FRACTION,
            ),
        );
        $rawFloat->assertUnprocessable()->assertJsonValidationErrors(['amount_ml']);
        $this->assertJsonContentType($rawFloat);

        $this->assertDatabaseCount('water_entries', 1);
        $this->assertSame(250, (int) WaterEntry::query()->firstOrFail()->amount_ml);

        $this->postJson('/v1/water-entries', ['date' => '2025-01-15', 'amount_ml' => 500])
            ->assertOk()
            ->assertJsonPath('data.amount_ml', 750);
    }
}
