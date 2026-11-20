<?php

declare(strict_types=1);

use App\Security\Middleware\AuthMiddleware;
use App\Users\Http\MeController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/*
 * /api/v1/me (P1). Contract: docs/api/openapi.yaml.
 * P2 adds /me/avatar, /me/usage and /me/export in their own routes file.
 *
 * Pending (unverified) users reach only GET /me; AuthMiddleware answers
 * 403 EMAIL_NOT_VERIFIED for the rest.
 *
 * @param App<\Psr\Container\ContainerInterface|null> $app
 */
return static function (App $app): void {
    // Not `static`: Slim binds group callbacks to the container, and a static closure can't be bound.
    $app->group('/api/v1/me', function (RouteCollectorProxy $me): void {
        $me->get('', [MeController::class, 'show']);
        $me->patch('', [MeController::class, 'update']);
        $me->delete('', [MeController::class, 'delete']);
        $me->post('/password', [MeController::class, 'changePassword']);
        $me->post('/email', [MeController::class, 'changeEmail']);
    })->add(AuthMiddleware::class);
};
