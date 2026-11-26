<?php

namespace App\Ship\Tests;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserGoal\Actions\SaveUserProfileAndGoalAction;
use App\Ship\Parents\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Shared harness for the product API tests introduced with task D.
 *
 * Every test uses fixed time and the isolated `ritm_test` schema. Helpers only
 * create data through the same actions the product uses, so historical goal
 * snapshots and timezone semantics stay realistic.
 */
abstract class ProductApiTestCase extends TestCase
{
    protected const DEFAULT_NOW = '2025-01-15 12:00:00 UTC';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::DEFAULT_NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'weight_kg' => 80,
            'height_cm' => 180,
            'birth_date' => '1990-01-15',
            'fat_percentage' => null,
            'gender' => 'male',
            'activity_level' => 'moderate',
            'goal' => 'lose_weight',
            'timezone' => 'Europe/Moscow',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createCompletedUser(array $overrides = [], string $localDate = '2025-01-15'): User
    {
        $user = User::factory()->createOne();

        $this->confirmProfile($user, $overrides, $localDate);

        return $user->fresh();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function confirmProfile(User $user, array $overrides = [], string $localDate = '2025-01-15'): UserGoal
    {
        $payload = $this->profilePayload($overrides);
        $timezone = (string) $payload['timezone'];

        Carbon::setTestNow(Carbon::parse($localDate . ' 12:00:00', $timezone));

        try {
            $result = app(SaveUserProfileAndGoalAction::class)->run($user, $payload);
        } finally {
            Carbon::setTestNow(Carbon::parse(self::DEFAULT_NOW));
        }

        return $result['goal'];
    }

    /**
     * Sanctum's session guard can cache the previously authenticated user
     * between requests in one test process. Forget the guards before switching
     * accounts so ownership/isolation assertions exercise a fresh request.
     */
    protected function actingAsUser(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh() ?? $user, 'web');
    }

    protected function forceCsrfValidation(): void
    {
        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app->make('encrypter')) extends ValidateCsrfToken {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
    }

    protected function assertJsonContentType($response): void
    {
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type'),
        );
        $this->assertFalse($response->headers->has('Location'));
    }
}
