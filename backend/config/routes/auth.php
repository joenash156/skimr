<?php

declare(strict_types=1);

use App\Auth\Http\AuthController;
use App\Security\Middleware\AuthMiddleware;
use App\Security\RateLimit\RateLimit;
use App\Security\RateLimit\RateLimiter;
use App\Security\Turnstile\TurnstileVerifier;
use Slim\App;

/*
 * /api/v1/auth (P1). Contract: docs/api/openapi.yaml.
 *
 * Slim runs the LAST-added middleware first, so each route reads bottom-up:
 * rate limit (chain step 3) -> Turnstile (step 4) -> handler.
 * Turnstile actions must match what the frontend passes to the widget:
 * register, login, forgot_password.
 *
 * @param App<\Psr\Container\ContainerInterface|null> $app
 */
return static function (App $app): void {
    $container = $app->getContainer() ?? throw new RuntimeException('The app must be built with a container.');
    /** @var TurnstileVerifier $turnstile */
    $turnstile = $container->get(TurnstileVerifier::class);
    /** @var RateLimiter $limiter */
    $limiter = $container->get(RateLimiter::class);

    $app->post('/api/v1/auth/register', [AuthController::class, 'register'])
        ->add($turnstile->middleware('register'))
        ->add($limiter->middleware(RateLimit::register(), RateLimiter::byIp()));

    $app->post('/api/v1/auth/login', [AuthController::class, 'login'])
        ->add($turnstile->middleware('login'))
        ->add($limiter->middleware(RateLimit::login(), RateLimiter::byBodyEmail(withIp: true)));

    $app->post('/api/v1/auth/password/forgot', [AuthController::class, 'forgotPassword'])
        ->add($turnstile->middleware('forgot_password'))
        ->add($limiter->middleware(RateLimit::forgotPassword(), RateLimiter::byBodyEmail()));

    // Cookie endpoints: CSRF-checked inside the controller (Origin + X-Requested-With).
    $app->post('/api/v1/auth/refresh', [AuthController::class, 'refresh']);
    $app->post('/api/v1/auth/logout', [AuthController::class, 'logout']);

    // Token-in-the-link endpoints: the 256-bit single-use token is the protection.
    $app->post('/api/v1/auth/verify-email', [AuthController::class, 'verifyEmail']);
    $app->post('/api/v1/auth/password/reset', [AuthController::class, 'resetPassword']);
    $app->post('/api/v1/auth/invite/accept', [AuthController::class, 'acceptInvite']);

    // Signed in (pending users allowed: AuthMiddleware lets them reach /auth/*).
    $app->post('/api/v1/auth/verify-email/resend', [AuthController::class, 'resendVerification'])
        ->add(AuthMiddleware::class);
};
