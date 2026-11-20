<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Security\RateLimit\RateLimit;
use App\Security\RateLimit\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * One named rate-limit bucket on one route (spec 11.4), counted before
 * Turnstile and the handler run (chain step 3 comes before step 4). Build it
 * from the limiter:
 *
 *     $limiter = $container->get(RateLimiter::class);
 *     $route->add($turnstile->middleware('contact'))
 *           ->add($limiter->middleware(RateLimit::contact(), RateLimiter::byIp()));
 *
 * Slim runs the last-added middleware first, so add the rate limit after Turnstile.
 */
final class RouteRateLimitMiddleware implements MiddlewareInterface
{
    /** @param \Closure(ServerRequestInterface): string $subject who is being counted, e.g. 'ip:203.0.113.5' */
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly RateLimit $limit,
        private readonly \Closure $subject,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->limiter->consume($this->limit, ($this->subject)($request));

        return $handler->handle($request);
    }
}
