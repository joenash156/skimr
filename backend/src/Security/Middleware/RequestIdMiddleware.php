<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Shared\Http\RequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 2 (spec 11): gives every request an id, returned as
 * X-Request-Id and included in error bodies and log lines (spec 11.2,
 * "Information leakage"). An incoming X-Request-Id is ignored, so clients
 * can't inject values into our logs.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = bin2hex(random_bytes(12));

        return $handler
            ->handle($request->withAttribute(RequestAttributes::REQUEST_ID, $id))
            ->withHeader('X-Request-Id', $id);
    }
}
