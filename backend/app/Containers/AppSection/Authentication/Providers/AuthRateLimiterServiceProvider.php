<?php

namespace App\Containers\AppSection\Authentication\Providers;

use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthRateLimiterServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('auth-register', static function (Request $request): Limit {
            return Limit::perMinute(10)
                ->by('auth-register|' . $request->ip())
                ->response(static fn (Request $request, array $headers): JsonResponse => response()->json(
                    ['message' => 'Слишком много попыток регистрации. Попробуйте позже.'],
                    429,
                    $headers,
                ));
        });

        RateLimiter::for('auth-login', static function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(10)
                ->by('auth-login|' . $email . '|' . $request->ip())
                ->response(static fn (Request $request, array $headers): JsonResponse => response()->json(
                    ['message' => 'Слишком много попыток входа. Попробуйте позже.'],
                    429,
                    $headers,
                ));
        });
    }
}
