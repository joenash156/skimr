<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Auth\Jwt\JwtService;
use App\Security\RateLimit\RateLimit;
use App\Security\RateLimit\RateLimiter;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Http\ClientIp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 3 (spec 11): the general API limit, 120 requests per minute
 * (spec 11.4, "any other API call").
 *
 * It runs before authentication, so it counts per user when the request has
 * a valid access token, and per IP otherwise. The specific buckets (login,
 * register, upload, contact...) need the request body or the signed-in user,
 * so the code handling those routes calls RateLimiter::consume() itself.
 *
 * Only /api/ paths are counted, and /api/v1/health is skipped so uptime
 * checks never trip it. Over the limit: 429 RATE_LIMITED with Retry-After.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private const SKIPPED = ['/api/v1/health'];

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly JwtService $jwt,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/') && !in_array($path, self::SKIPPED, true)) {
            $this->limiter->consume(RateLimit::api(), $this->subject($request));
        }

        return $handler->handle($request);
    }

    private function subject(ServerRequestInterface $request): string
    {
        if (preg_match('/^Bearer\s+(\S+)$/', $request->getHeaderLine('Authorization'), $m)) {
            try {
                return 'user:' . $this->jwt->verifyAccessToken($m[1])->userUuid;
            } catch (UnauthorizedException) {
                // Invalid or expired token: count it against the IP; AuthMiddleware answers 401 later.
            }
        }

        return 'ip:' . (ClientIp::of($request) ?? 'unknown');
    }
}
