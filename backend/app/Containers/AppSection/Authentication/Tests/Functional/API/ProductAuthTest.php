<?php

namespace App\Containers\AppSection\Authentication\Tests\Functional\API;

use App\Containers\AppSection\Authentication\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ProductAuthTest extends ApiTestCase
{
    public function testRegisterCreatesUserAndServerSessionWithoutReturningSecretsOrTokens(): void
    {
        $response = $this->postJson('/v1/auth/register', [
            'email' => 'New.User@Example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'new.user@example.com')
            ->assertJsonPath('data.profile_completed', false)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $payload = $response->json();
        $this->assertArrayNotHasKey('access_token', $payload);
        $this->assertArrayNotHasKey('refresh_token', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('password', $payload['data']);

        $user = User::query()->where('email', 'new.user@example.com')->firstOrFail();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertFalse($user->profile_completed);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function testRegisterCannotSetProfileCompletedFromInput(): void
    {
        $response = $this->postJson('/v1/auth/register', [
            'email' => 'completed@example.com',
            'password' => 'password123',
            'profile_completed' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.profile_completed', false);
        $this->assertFalse(User::query()->where('email', 'completed@example.com')->firstOrFail()->profile_completed);
    }

    public function testRegisterRejectsDuplicateEmailCaseInsensitively(): void
    {
        User::factory()->createOne(['email' => 'taken@example.com']);

        $response = $this->postJson('/v1/auth/register', [
            'email' => 'TAKEN@Example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());
    }

    public function testRegisterValidatesEmailAndPassword(): void
    {
        $response = $this->postJson('/v1/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function testLoginWithValidCredentialsAndCompletionContract(): void
    {
        $user = User::factory()->createOne([
            'email' => 'gandalf@example.com',
            'password' => 'password123',
        ]);
        $user->forceFill(['profile_completed' => true])->saveQuietly();

        $response = $this->postJson('/v1/auth/login', [
            'email' => 'GANDALF@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'gandalf@example.com')
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonMissingPath('data.password');

        $payload = $response->json();
        $this->assertArrayNotHasKey('access_token', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function testLoginRejectsWrongCredentials(): void
    {
        User::factory()->createOne([
            'email' => 'gandalf@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/v1/auth/login', [
            'email' => 'gandalf@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertGuest('web');
    }

    public function testGuestMeReturnsJsonUnauthorized(): void
    {
        $response = $this->getJson('/v1/me');

        $response->assertUnauthorized()->assertJsonStructure(['message']);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    }

    public function testGuestMeWithoutAcceptReturnsJson401WithoutRedirect(): void
    {
        // Symfony's test factory adds a browser Accept by default; an empty
        // value emulates a client that sends no Accept header at all.
        $response = $this->get('/v1/me', ['Accept' => '']);

        $response->assertUnauthorized()->assertJsonStructure(['message']);
        $this->assertJsonResponseWithoutRedirect($response);
    }

    public function testGuestMeWithHtmlAcceptReturnsJson401WithoutRedirect(): void
    {
        $response = $this->get('/v1/me', ['Accept' => 'text/html']);

        $response->assertUnauthorized()->assertJsonStructure(['message']);
        $this->assertJsonResponseWithoutRedirect($response);
    }

    public function testValidationErrorsWithoutJsonAcceptStayJson(): void
    {
        $response = $this->post('/v1/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
        ], ['Accept' => 'text/html']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->assertJsonResponseWithoutRedirect($response);
    }

    public function testGuestLogoutWithoutCsrfReturnsJson419(): void
    {
        $this->forceCsrfValidation();

        $response = $this->post('/v1/auth/logout', [], ['Accept' => 'text/html']);

        $response->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertJsonResponseWithoutRedirect($response);
    }

    public function testGuestLogoutWithValidCsrfReturnsJson401(): void
    {
        $this->forceCsrfValidation();
        $this->withSession(['_token' => 'csrf-test-token']);

        $response = $this->post('/v1/auth/logout', [], [
            'Accept' => 'text/html',
            'X-CSRF-TOKEN' => 'csrf-test-token',
        ]);

        $response->assertUnauthorized()->assertJsonStructure(['message']);
        $this->assertJsonResponseWithoutRedirect($response);
    }

    public function testFirstUserOnlySeesOwnMe(): void
    {
        $first = User::factory()->createOne(['email' => 'first@example.com']);
        User::factory()->createOne(['email' => 'second@example.com']);

        $this->actingAs($first, 'web');
        $this->getJson('/v1/me')->assertOk()->assertJsonPath('data.email', 'first@example.com');
    }

    public function testSecondUserOnlySeesOwnMe(): void
    {
        User::factory()->createOne(['email' => 'first@example.com']);
        $second = User::factory()->createOne(['email' => 'second@example.com']);

        $this->actingAs($second, 'web');
        $this->getJson('/v1/me')->assertOk()->assertJsonPath('data.email', 'second@example.com');
    }

    public function testRegisterSessionIsRegenerated(): void
    {
        $this->withSession(['sentinel' => 'before']);
        $before = app('session.store')->getId();

        $this->postJson('/v1/auth/register', [
            'email' => 'regenerated@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $session = app('session.store');
        $this->assertNotSame($before, $session->getId());
        $this->assertAuthenticated('web');
    }

    public function testLoginSessionIsRegenerated(): void
    {
        User::factory()->createOne([
            'email' => 'regenerated-login@example.com',
            'password' => 'password123',
        ]);
        $this->withSession(['sentinel' => 'before']);
        $before = app('session.store')->getId();

        $this->postJson('/v1/auth/login', [
            'email' => 'regenerated-login@example.com',
            'password' => 'password123',
        ])->assertOk();

        $session = app('session.store');
        $this->assertNotSame($before, $session->getId());
        $this->assertAuthenticated('web');
    }

    public function testLogoutInvalidatesSessionAndRotatesCsrfToken(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user, 'web');
        $this->withSession(['sentinel' => 'before', '_token' => 'csrf-before']);
        $session = app('session.store');
        $beforeId = $session->getId();
        $beforeToken = $session->token();

        $this->postJson('/v1/auth/logout')->assertNoContent();

        $this->assertGuest('web');
        $this->assertNotSame($beforeId, $session->getId());
        $this->assertNotSame($beforeToken, $session->token());
        $this->assertFalse($session->has('sentinel'));
    }

    private function assertJsonResponseWithoutRedirect(TestResponse $response): void
    {
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
        $this->assertFalse($response->headers->has('Location'));
    }

    /**
     * The framework skips CSRF verification while APP_ENV=testing. Replace the
     * middleware only for this test so the real token comparison runs.
     */
    private function forceCsrfValidation(): void
    {
        $this->app->forgetInstance(ValidateCsrfToken::class);
        $this->app->bind(ValidateCsrfToken::class, static function ($app): ValidateCsrfToken {
            return new class($app, $app->make('encrypter')) extends ValidateCsrfToken {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
    }

    public function testRegisterAndLoginHaveSeparateRateLimiters(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/v1/auth/register', [
                'email' => 'invalid',
                'password' => 'short',
            ])->assertUnprocessable();
        }

        $this->postJson('/v1/auth/register', [
            'email' => 'invalid',
            'password' => 'short',
        ])->assertTooManyRequests()
            ->assertJsonPath('message', 'Слишком много попыток регистрации. Попробуйте позже.');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/v1/auth/login', [
                'email' => 'blocked@example.com',
                'password' => 'wrong',
            ])->assertUnprocessable();
        }

        $this->postJson('/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'wrong',
        ])->assertTooManyRequests()
            ->assertJsonPath('message', 'Слишком много попыток входа. Попробуйте позже.');
    }
}
