<?php

namespace App\Containers\AppSection\UserGoal\Tests\Functional;

use App\Containers\AppSection\UserGoal\Tests\FunctionalTestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class ApiTestCase extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'UTC'));
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
    protected function payload(array $overrides = []): array
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
}
