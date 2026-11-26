<?php

namespace App\Containers\AppSection\Authentication\Tests\Functional\WEB;

use App\Containers\AppSection\Authentication\Tests\Functional\WebTestCase;
use App\Containers\AppSection\Authentication\UI\WEB\Controllers\HomePageController;
use App\Containers\AppSection\Authentication\UI\WEB\Controllers\LoginController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Cookie;

#[CoversClass(LoginController::class)]
final class LoginTest extends WebTestCase
{
    public function testCanLoginWithCredentials(): void
    {
        $user = User::factory()->createOne([
            'email' => 'gandalf@the.grey',
            'password' => 'youShallNotPass',
        ]);

        $response = $this
            ->post(action(LoginController::class), [
                'email' => $user->email,
                'password' => 'youShallNotPass',
                'remember' => true,
            ])
        ;

        $response->assertRedirect(action(HomePageController::class));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function testCanLoginViaRememberCookie(): void
    {
        $user = User::factory()->createOne([
            'email' => 'gandalf@the.grey',
            'password' => 'youShallNotPass',
        ]);

        $response = $this
            ->post(action(LoginController::class), [
                'email' => $user->email,
                'password' => 'youShallNotPass',
                'remember' => true,
            ])
        ;

        $response->assertRedirect(action(HomePageController::class));
        $this->assertAuthenticatedAs($user, 'web');

        $rememberCookieName = Auth::guard('web')->getRecallerName();
        $response->assertCookie($rememberCookieName);
        /** @var Cookie $rememberCookie */
        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === $rememberCookieName);
        $this->assertNotNull($rememberCookie);

        $this->flushSession();
        Auth::forgetGuards();

        Route::get('test-remember-cookie', static function () {
            return response()->json([
                'viaRemember' => Auth::guard('web')->viaRemember(),
                'user' => Auth::guard('web')->id(),
            ]);
        })->middleware(['web', 'auth:web']);

        // The response cookie is already encrypted by EncryptCookies; send it
        // back verbatim so the middleware decrypts it like a browser would.
        $response = $this
            ->withUnencryptedCookie($rememberCookieName, $rememberCookie->getValue())
            ->get('test-remember-cookie')
            ->assertOk();

        $this->assertTrue($response->json('viaRemember'));
        $this->assertSame($user->id, $response->json('user'));
        $this->assertAuthenticatedAs($user, 'web');
    }
}
