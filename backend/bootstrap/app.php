<?php

use Apiato\Foundation\Apiato;
use Apiato\Http\Middleware\ProcessETag;
use Apiato\Http\Middleware\ValidateJsonContent;
use App\Containers\AppSection\Authentication\UI\WEB\Controllers\HomePageController;
use App\Containers\AppSection\Authentication\UI\WEB\Controllers\LoginController;
use App\Ship\Middleware\ForceJsonResponse;
use App\Ship\Middleware\ValidateAppId;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$basePath = dirname(__DIR__);
$apiato = Apiato::configure(basePath: $basePath)->create();

return Application::configure(basePath: $basePath)
    ->withProviders($apiato->providers())
    ->withEvents($apiato->events())
    ->withRouting(
        web: $apiato->webRoutes(),
        channels: __DIR__ . '/../app/Ship/Broadcasting/channels.php',
        health: '/up',
        then: static fn () => $apiato->registerApiRoutes(),
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            ValidateAppId::class,
        ]);
        $middleware->api(append: [
            ValidateJsonContent::class,
            ProcessETag::class,
        ]);
        /*
         * Product auth routes use a real server session and always validate
         * CSRF, even when a request has no Origin/Referer header. Sanctum's
         * statefulApi() only enables this for requests it detects as coming
         * from SANCTUM_STATEFUL_DOMAINS, so the product routes use an explicit
         * group instead. The `auth:sanctum` guard still reads this web session.
         */
        $middleware->group('stateful-api', [
            ForceJsonResponse::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);
        /*
         * Route middleware are sorted by priority, and the framework's default
         * priority list puts Authenticate before our non-priority group. That
         * made ForceJsonResponse and ValidateCsrfToken run after auth, so a
         * guest request without Accept was redirected to the WEB login page
         * and a guest logout without CSRF returned 401 instead of 419. Pin the
         * stateful API middleware to the documented order without changing
         * the global WEB/API middleware stacks or forcing JSON on WEB routes.
         */
        $middleware->prependToPriorityList(EncryptCookies::class, ForceJsonResponse::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ValidateCsrfToken::class);
        $middleware->redirectUsersTo(static function (Request $request): string {
            return action(HomePageController::class);
        });
        $middleware->redirectGuestsTo(static function (Request $request): string {
            return action([LoginController::class, 'showForm']);
        });
    })
    ->withCommands($apiato->commands())
    ->withExceptions(static function (Exceptions $exceptions) {})
    ->create();
