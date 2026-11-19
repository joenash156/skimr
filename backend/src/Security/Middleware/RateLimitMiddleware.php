<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 3 (spec 11): rate limiting.
 *
 * B0 stub: passes every request through. B1 replaces the body with the
 * database-backed RateLimiter and the named buckets in spec 11.4, throwing
 * QuotaExceededException::rateLimited() (429 + Retry-After) when a bucket is full.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
