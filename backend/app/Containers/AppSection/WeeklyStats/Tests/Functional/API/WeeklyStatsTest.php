<?php

namespace App\Containers\AppSection\WeeklyStats\Tests\Functional\API;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Tests\ProductApiTestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class WeeklyStatsTest extends ProductApiTestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private function addFood(User $user, string $date, float $calories, array $overrides = []): FoodEntry
    {
        return FoodEntry::query()->create(array_merge([
            'user_id' => $user->getKey(),
            'date' => $date,
            'name' => 'Приём пищи',
            'calories' => $calories,
            'protein' => 10,
            'fat' => 5,
            'carbs' => 20,
        ], $overrides));
    }

    public function testGuestCannotReadWeeklyStats(): void
    {
        $this->getJson('/v1/weekly-stats')->assertUnauthorized();

        $response = $this->get('/v1/weekly-stats', ['Accept' => 'text/html']);
        $response->assertUnauthorized()->assertJsonStructure(['message']);
        $this->assertJsonContentType($response);
    }

    public function testInvalidStartIsRejected(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        foreach (['2025-02-30', '2025-13-01', '31.12.2024', 'not-a-date'] as $start) {
            $this->getJson('/v1/weekly-stats?start=' . urlencode($start))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['start']);
        }
    }

    public function testAlwaysReturnsSevenCanonicalMondayToSundayDays(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $expected = [
            '2025-01-13',
            '2025-01-14',
            '2025-01-15',
            '2025-01-16',
            '2025-01-17',
            '2025-01-18',
            '2025-01-19',
        ];

        foreach (['2025-01-15', '2025-01-13', '2025-01-19', '2025-01-16'] as $start) {
            $response = $this->getJson('/v1/weekly-stats?start=' . $start)->assertOk();
            $response
                ->assertJsonPath('data.start', '2025-01-13')
                ->assertJsonPath('data.end', '2025-01-19')
                ->assertJsonCount(7, 'data.days');

            $this->assertSame($expected, array_column($response->json('data.days'), 'date'));
        }
    }

    public function testDefaultWeekUsesProfileTimezone(): void
    {
        $losAngeles = $this->createCompletedUser(['timezone' => 'America/Los_Angeles']);
        $tokyo = $this->createCompletedUser(['timezone' => 'Asia/Tokyo']);

        Carbon::setTestNow(Carbon::parse('2025-01-13 01:00:00', 'UTC'));

        $this->actingAsUser($losAngeles);
        $this->getJson('/v1/weekly-stats')
            ->assertOk()
            ->assertJsonPath('data.today', '2025-01-12')
            ->assertJsonPath('data.start', '2025-01-06')
            ->assertJsonPath('data.end', '2025-01-12');

        $this->actingAsUser($tokyo);
        $this->getJson('/v1/weekly-stats')
            ->assertOk()
            ->assertJsonPath('data.today', '2025-01-13')
            ->assertJsonPath('data.start', '2025-01-13')
            ->assertJsonPath('data.end', '2025-01-19');
    }

    public function testNoFoodIsNullButZeroCalorieEntryIsZero(): void
    {
        $user = $this->createCompletedUser([], '2025-01-10');
        $this->actingAsUser($user);

        $this->addFood($user, '2025-01-14', 0);

        $response = $this->getJson('/v1/weekly-stats?start=2025-01-13')->assertOk();

        $response
            ->assertJsonPath('data.days.0.date', '2025-01-13')
            ->assertJsonPath('data.days.0.eaten', null)
            ->assertJsonPath('data.days.0.has_entries', false)
            ->assertJsonPath('data.days.0.progress', null);

        $response
            ->assertJsonPath('data.days.1.date', '2025-01-14')
            ->assertJsonPath('data.days.1.eaten', 0)
            ->assertJsonPath('data.days.1.has_entries', true)
            ->assertJsonPath('data.days.1.progress', 0);

        $this->assertTrue($response->json('data.has_entries'));
    }

    public function testDayTargetsComeFromHistoryAndWeekTotalsMatchDashboard(): void
    {
        $user = $this->createCompletedUser([], '2025-01-10');
        $this->confirmProfile($user, ['weight_kg' => 90], '2025-01-15');
        $this->actingAsUser($user);

        $this->addFood($user, '2025-01-13', 100);
        $this->addFood($user, '2025-01-14', 200.5);
        $this->addFood($user, '2025-01-15', 300);
        ActivityEntry::query()->create([
            'user_id' => $user->getKey(),
            'date' => '2025-01-15',
            'name' => 'Тренировка',
            'calories' => 500,
            'steps' => 8000,
            'minutes' => 60,
            'distance_km' => 6,
        ]);

        $weekly = $this->getJson('/v1/weekly-stats?start=2025-01-13')->assertOk();

        $weekly
            ->assertJsonPath('data.days.0.target', 2320)
            ->assertJsonPath('data.days.1.target', 2320)
            ->assertJsonPath('data.days.2.target', 2440)
            ->assertJsonPath('data.days.3.target', 2440)
            ->assertJsonPath('data.days.2.eaten', 300)
            ->assertJsonPath('data.eaten_total', 600.5)
            ->assertJsonPath('data.has_entries', true);

        $dashboard = $this->getJson('/v1/dashboard?date=2025-01-15')->assertOk();
        $this->assertSame(
            $dashboard->json('data.calories.eaten'),
            $weekly->json('data.days.2.eaten'),
        );
        $this->assertSame(
            $dashboard->json('data.target.calories'),
            $weekly->json('data.days.2.target'),
        );
        $this->assertSame(600.5, $weekly->json('data.eaten_total'));
    }

    public function testOverTargetCapsProgressAndAddsExplicitExcessFlag(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $this->addFood($user, '2025-01-15', 3000);

        $this->getJson('/v1/weekly-stats?start=2025-01-13')
            ->assertOk()
            ->assertJsonPath('data.days.2.target', 2310)
            ->assertJsonPath('data.days.2.eaten', 3000)
            ->assertJsonPath('data.days.2.progress', 1)
            ->assertJsonPath('data.days.2.is_over_target', true)
            ->assertJsonPath('data.days.2.excess', 690);
    }

    public function testNoTargetMeansNoFakeProgressOrDivisionByZero(): void
    {
        $user = $this->createCompletedUser([], '2025-01-15');
        $this->actingAsUser($user);

        $this->addFood($user, '2025-01-13', 250);
        $this->addFood($user, '2025-01-15', 500);

        $this->getJson('/v1/weekly-stats?start=2025-01-13')
            ->assertOk()
            ->assertJsonPath('data.days.0.target', null)
            ->assertJsonPath('data.days.0.eaten', 250)
            ->assertJsonPath('data.days.0.progress', null)
            ->assertJsonPath('data.days.0.is_over_target', false)
            ->assertJsonPath('data.days.0.excess', null)
            ->assertJsonPath('data.days.2.target', 2310)
            ->assertJsonPath('data.days.2.progress', 0.2165)
            ->assertJsonPath('data.eaten_total', 750);
    }

    public function testDataIsIsolatedBetweenUsers(): void
    {
        $user = $this->createCompletedUser();
        $other = $this->createCompletedUser();
        $this->actingAsUser($other);
        $this->addFood($other, '2025-01-15', 999);

        $this->actingAsUser($user);
        $this->getJson('/v1/weekly-stats?start=2025-01-13')
            ->assertOk()
            ->assertJsonPath('data.has_entries', false)
            ->assertJsonPath('data.eaten_total', 0)
            ->assertJsonPath('data.days.2.eaten', null);
    }

    public function testMonthAndYearBoundariesStaySevenDays(): void
    {
        $user = $this->createCompletedUser();
        $this->actingAsUser($user);

        $response = $this->getJson('/v1/weekly-stats?start=2024-12-30')->assertOk();
        $response
            ->assertJsonPath('data.start', '2024-12-30')
            ->assertJsonPath('data.end', '2025-01-05')
            ->assertJsonCount(7, 'data.days');

        $response = $this->getJson('/v1/weekly-stats?start=2025-12-31')->assertOk();
        $response
            ->assertJsonPath('data.start', '2025-12-29')
            ->assertJsonPath('data.end', '2026-01-04');
        $this->assertSame(
            ['2025-12-29', '2025-12-30', '2025-12-31', '2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'],
            array_column($response->json('data.days'), 'date'),
        );
    }

    public function testDstTransitionKeepsExactlySevenUniqueDates(): void
    {
        $user = $this->createCompletedUser(['timezone' => 'America/New_York']);
        $this->actingAsUser($user);

        $response = $this->getJson('/v1/weekly-stats?start=2025-03-09')->assertOk();
        $dates = array_column($response->json('data.days'), 'date');

        $this->assertSame([
            '2025-03-03',
            '2025-03-04',
            '2025-03-05',
            '2025-03-06',
            '2025-03-07',
            '2025-03-08',
            '2025-03-09',
        ], $dates);
        $this->assertCount(7, array_unique($dates));
    }
}
