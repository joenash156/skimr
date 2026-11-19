<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 4 (spec 11): Cloudflare Turnstile, opt-in per route:
 *
 *     $app->post('/api/v1/auth/register', ...)->add(new TurnstileMiddleware('register'));
 *
 * B0 stub that fails closed: until B1 adds TurnstileVerifier (checking
 * success, hostname and action), any route using it refuses every request
 * instead of silently skipping the bot check.
 */
final class TurnstileMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $action)
    {
    }

    public function action(): string
    {
        return $this->action;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new \LogicException('TurnstileMiddleware is not implemented yet (P1, batch B1).');
    }
}
