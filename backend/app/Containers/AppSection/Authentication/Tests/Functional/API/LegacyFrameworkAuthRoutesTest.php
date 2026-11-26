<?php

namespace App\Containers\AppSection\Authentication\Tests\Functional\API;

use App\Containers\AppSection\Authentication\Tests\Functional\ApiTestCase;

final class LegacyFrameworkAuthRoutesTest extends ApiTestCase
{
    public function testFrameworkRegistrationAndTokenRoutesAreNotExternallyAvailable(): void
    {
        $routes = [
            '/v1/register',
            '/v1/logout',
            '/v1/clients/web/login',
            '/v1/clients/web/refresh',
            '/v1/oauth/token',
        ];

        foreach ($routes as $route) {
            $this->postJson($route, [
                'email' => 'legacy@example.com',
                'password' => 'password123',
            ])->assertNotFound();
        }
    }

    public function testProductAuthRoutesAreRegistered(): void
    {
        $this->postJson('/v1/auth/register')->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/v1/auth/login')->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->getJson('/v1/me')->assertUnauthorized();
    }
}
