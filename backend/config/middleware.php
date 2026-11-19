<?php

declare(strict_types=1);

use App\Security\Middleware\RateLimitMiddleware;
use App\Security\Middleware\RequestIdMiddleware;
use App\Security\Middleware\SecurityHeadersMiddleware;
use App\Shared\Http\ErrorHandler;
use Slim\App;

/*
 * The global middleware chain (spec 11). Owned by P1; nobody bypasses it.
 *
 * Slim runs middleware in REVERSE order of adding (last added = outermost),
 * so this file adds them innermost first. A request flows:
 *
 *   1 SecurityHeaders -> 2 RequestId -> [ErrorMiddleware] -> 3 RateLimit
 *     -> routing -> per-route: 4 Turnstile -> 5 Auth -> 6 RoleGuard
 *     -> handler (7 policy checks and 8 validation happen in services and request DTOs)
 *
 * ErrorMiddleware sits inside RequestId and SecurityHeaders, so error
 * responses still get a request id and the full set of security headers.
 *
 * @param App<\Psr\Container\ContainerInterface|null> $app
 */
return static function (App $app): void {
    $container = $app->getContainer();
    if ($container === null) {
        throw new RuntimeException('The app must be built with a container.');
    }

    /** @var array{app: array{debug: bool}} $settings */
    $settings = $container->get('settings');
    $debug = $settings['app']['debug'];

    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(RateLimitMiddleware::class);

    $errors = $app->addErrorMiddleware($debug, true, $debug);
    $errors->setDefaultErrorHandler($container->get(ErrorHandler::class));

    $app->add(RequestIdMiddleware::class);
    $app->add(SecurityHeadersMiddleware::class);
};
