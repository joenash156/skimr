<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Shared\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 5 (spec 11): authentication. Add it to a route or group:
 *
 *     $group->...->add(AuthMiddleware::class);
 *
 * B0 stub that fails closed: every request is 401 until B1 verifies the
 * access JWT (signature, exp, iss, aud, tv == users.token_version,
 * status == active) and sets the "user" request attribute.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new UnauthorizedException();
    }
}
