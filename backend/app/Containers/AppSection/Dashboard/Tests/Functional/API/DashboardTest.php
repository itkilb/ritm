<?php

namespace App\Containers\AppSection\Dashboard\Tests\Functional\API;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\WaterEntry\Models\WaterEntry;
use App\Ship\Tests\ProductApiTestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class DashboardTest extends ProductApiTestCase
{
    public function testGuestCannotReadDashboard(): void
    {
        $this->getJson('/v1/dashboard')->assertUnauthorized();

        $response = $this->get('/v1/dashboard', ['Accept' => 'text/html']);
        $response->assertUnauthorized()->assertJsonStructure(['message']);
        $this->assertJsonContentType($response);
    }

    public function testInvalidOrImpossibleDateIsRejected(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        foreach (['2025-02-30', '2025-13-01', '15.01.2025', '2025-1-5', 'yesterday'] as $date) {
            $this->getJson('/v1/dashboard?date=' . urlencode($date))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['date']);
        }
    }

    public function testDefaultDateUsesProfileTimezoneNotUtc(): void
    {
        $losAngeles = $this->createCompletedUser(['timezone' => 'America/Los_Angeles']);
        $tokyo = $this->createCompletedUser(['timezone' => 'Asia/Tokyo']);

        Carbon::setTestNow(Carbon::parse('2025-01-16 02:00:00', 'UTC'));

        $this->actingAsUser($losAngeles);
        $this->getJson('/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/Los_Angeles')
            ->assertJsonPath('data.date', '2025-01-15')
            ->assertJsonPath('data.today', '2025-01-15');

        $this->actingAsUser($tokyo);
        $this->getJson('/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Tokyo')
            ->assertJsonPath('data.date', '2025-01-16')
            ->assertJsonPath('data.today', '2025-01-16');
    }

    public function testCalendarDateNotCreatedAtDrivesTheDay(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $entry = FoodEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-10',
            'name' => 'Старая запись',
            'calories' => 100,
            'protein' => 1,
            'fat' => 1,
            'carbs' => 1,
        ]);
        FoodEntry::query()->whereKey($entry->getKey())->update(['created_at' => '2025-02-01 10:00:00']);

        $this->getJson('/v1/dashboard?date=2025-01-10')
            ->assertOk()
            ->assertJsonPath('data.food.entries.0.name', 'Старая запись')
            ->assertJsonPath('data.calories.eaten', 100);

        $this->getJson('/v1/dashboard?date=2025-02-01')
            ->assertOk()
            ->assertJsonPath('data.food.has_entries', false)
            ->assertJsonPath('data.calories.eaten', 0);
    }

    public function testHistoricalTargetsComesFromGoalHistoryAndMacrosMatchThatDay(): void
    {
        $user = $this->createCompletedUser([], '2025-01-10');
        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user);

        $this->getJson('/v1/dashboard?date=2025-01-12')
            ->assertOk()
            ->assertJsonPath('data.target.calories', 2320)
            ->assertJsonPath('data.calories.target', 2320)
            ->assertJsonPath('data.macros.protein.target', 128)
            ->assertJsonPath('data.target.effective_date', '2025-01-10');

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.target.calories', 2440)
            ->assertJsonPath('data.macros.protein.target', 144)
            ->assertJsonPath('data.target.effective_date', '2025-01-15');

        $this->getJson('/v1/dashboard?date=2025-01-09')
            ->assertOk()
            ->assertJsonPath('data.target', null)
            ->assertJsonPath('data.calories.target', null);
    }

    public function testDayAggregatesSeparateActivityAndRespectHistoricalTarget(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        FoodEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Завтрак',
            'calories' => 400.25,
            'protein' => 20.5,
            'fat' => 10.25,
            'carbs' => 50.5,
        ]);
        FoodEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Обед',
            'calories' => 600.75,
            'protein' => 30.25,
            'fat' => 20.5,
            'carbs' => 60.25,
        ]);

        ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Прогулка',
            'calories' => 180.5,
            'steps' => 6500,
            'minutes' => 45.5,
            'distance_km' => 4.325,
        ]);
        ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Лестница',
            'calories' => 19.5,
            'steps' => 5000,
            'minutes' => 14.5,
            'distance_km' => 0.675,
        ]);

        WaterEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'amount_ml' => 250,
        ]);

        $response = $this->getJson('/v1/dashboard?date=2025-01-15')->assertOk();

        $response
            ->assertJsonPath('data.target.calories', 2310)
            ->assertJsonPath('data.food.totals.calories', 1001)
            ->assertJsonPath('data.food.totals.protein', 50.75)
            ->assertJsonPath('data.food.totals.fat', 30.75)
            ->assertJsonPath('data.food.totals.carbs', 110.75)
            ->assertJsonPath('data.activity.totals.calories', 200)
            ->assertJsonPath('data.activity.totals.steps', 11500)
            ->assertJsonPath('data.activity.totals.minutes', 60)
            ->assertJsonPath('data.activity.totals.distance_km', 5)
            ->assertJsonPath('data.calories.eaten', 1001)
            ->assertJsonPath('data.calories.target', 2310)
            ->assertJsonPath('data.calories.remaining', 1309)
            ->assertJsonPath('data.calories.excess', 0)
            ->assertJsonPath('data.calories.activity', 200)
            ->assertJsonPath('data.macros.protein.consumed', 50.75)
            ->assertJsonPath('data.macros.protein.target', 128)
            ->assertJsonPath('data.steps.total', 11500)
            ->assertJsonPath('data.steps.goal', 10000)
            ->assertJsonPath('data.steps.remaining', 0)
            ->assertJsonPath('data.steps.minutes', 60)
            ->assertJsonPath('data.steps.distance_km', 5)
            ->assertJsonPath('data.water.amount_ml', 250)
            ->assertJsonPath('data.water.target_ml', 2000)
            ->assertJsonPath('data.food.has_entries', true);
    }

    public function testDeletingFoodRemovesItFromDayAggregates(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $created = $this->postJson('/v1/food-entries', [
            'date' => '2025-01-15',
            'name' => 'Удаляемый обед',
            'calories' => 450.75,
            'protein' => 20,
            'fat' => 15,
            'carbs' => 50,
        ])->assertCreated();

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.eaten', 450.75)
            ->assertJsonPath('data.food.has_entries', true);

        $this->deleteJson('/v1/food-entries/' . $created->json('data.id'))->assertNoContent();

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.eaten', 0)
            ->assertJsonPath('data.food.has_entries', false)
            ->assertJsonPath('data.food.totals.calories', 0)
            ->assertJsonPath('data.calories.remaining', 2310);
    }

    public function testExcessIsReportedAndRemainingNeverNegative(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        FoodEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Праздничный ужин',
            'calories' => 2500,
            'protein' => 100,
            'fat' => 100,
            'carbs' => 200,
        ]);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.calories.target', 2310)
            ->assertJsonPath('data.calories.eaten', 2500)
            ->assertJsonPath('data.calories.remaining', 0)
            ->assertJsonPath('data.calories.excess', 190);
    }

    public function testNullTargetBeforeFirstGoalDoesNotInventZeroNorm(): void
    {
        $user = $this->createCompletedUser([], '2025-01-15');
        $this->actingAsUser($user);

        FoodEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-14',
            'name' => 'Вчерашний перекус',
            'calories' => 123.45,
            'protein' => 5,
            'fat' => 4,
            'carbs' => 15,
        ]);

        $this->getJson('/v1/dashboard?date=2025-01-14')
            ->assertOk()
            ->assertJsonPath('data.target', null)
            ->assertJsonPath('data.calories.target', null)
            ->assertJsonPath('data.calories.eaten', 123.45)
            ->assertJsonPath('data.calories.remaining', null)
            ->assertJsonPath('data.calories.excess', null)
            ->assertJsonPath('data.macros.protein.consumed', 5)
            ->assertJsonPath('data.macros.protein.target', null)
            ->assertJsonPath('data.food.has_entries', true);
    }

    public function testDataIsIsolatedBetweenUsers(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($other);

        FoodEntry::query()->create([
            'user_id' => $other->getKey(),
            'date' => '2025-01-15',
            'name' => 'Чужое',
            'calories' => 999,
            'protein' => 99,
            'fat' => 99,
            'carbs' => 99,
        ]);
        ActivityEntry::query()->create([
            'user_id' => $other->getKey(),
            'date' => '2025-01-15',
            'name' => 'Чужая активность',
            'calories' => 500,
            'steps' => 9000,
            'minutes' => 60,
            'distance_km' => 5,
        ]);
        WaterEntry::query()->create([
            'user_id' => $other->getKey(),
            'date' => '2025-01-15',
            'amount_ml' => 1500,
        ]);

        $this->actingAsUser($user);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.food.has_entries', false)
            ->assertJsonPath('data.calories.eaten', 0)
            ->assertJsonPath('data.activity.totals.calories', 0)
            ->assertJsonPath('data.activity.totals.steps', 0)
            ->assertJsonPath('data.water.amount_ml', 0);
    }

    public function testProfileChangeKeepsEntriesAndPastTargetsWhileNewDaysUseNewGoal(): void
    {
        $user = $this->createCompletedUser([], '2025-01-10');
        $this->actingAsUser($user);

        FoodEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-10',
            'name' => 'Прошлый завтрак',
            'calories' => 300,
            'protein' => 10,
            'fat' => 10,
            'carbs' => 30,
        ]);
        ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-10',
            'name' => 'Прошлая прогулка',
            'calories' => 100,
            'steps' => 3000,
            'minutes' => 30,
            'distance_km' => 2,
        ]);
        WaterEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-10',
            'amount_ml' => 500,
        ]);

        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');

        $this->actingAsUser($user->fresh());

        $this->getJson('/v1/dashboard?date=2025-01-10')
            ->assertOk()
            ->assertJsonPath('data.target.calories', 2320)
            ->assertJsonPath('data.food.totals.calories', 300)
            ->assertJsonPath('data.activity.totals.calories', 100)
            ->assertJsonPath('data.water.amount_ml', 500);

        $this->getJson('/v1/dashboard?date=2025-01-15')
            ->assertOk()
            ->assertJsonPath('data.target.calories', 2440)
            ->assertJsonPath('data.food.has_entries', false);

        $this->assertSame(2, UserGoal::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(1, FoodEntry::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(1, ActivityEntry::query()->where('user_id', $user->getKey())->count());
        $this->assertSame(1, WaterEntry::query()->where('user_id', $user->getKey())->count());
    }

    public function testDstTransitionDoesNotShiftTheCalendarDay(): void
    {
        $user = $this->createCompletedUser(['timezone' => 'America/New_York']);

        Carbon::setTestNow(Carbon::parse('2025-03-09 06:30:00', 'UTC'));
        $this->actingAsUser($user);
        $this->getJson('/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today', '2025-03-09')
            ->assertJsonPath('data.date', '2025-03-09');

        Carbon::setTestNow(Carbon::parse('2025-03-10 03:30:00', 'UTC'));
        $this->getJson('/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today', '2025-03-09');

        Carbon::setTestNow(Carbon::parse('2025-03-10 04:30:00', 'UTC'));
        $this->getJson('/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today', '2025-03-10');
    }

    public function testFallbackTimezoneWhenProfileRowIsMissing(): void
    {
        $user = User::factory()->createOne();
        $this->actingAsUser($user);

        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2025-01-16 01:00:00', 'UTC'));

        $this->getJson('/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'UTC')
            ->assertJsonPath('data.today', '2025-01-16')
            ->assertJsonPath('data.target', null);
    }
}
