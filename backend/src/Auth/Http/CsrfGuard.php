<?php

declare(strict_types=1);

namespace App\Auth\Http;

use App\Shared\Exception\ForbiddenException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CSRF check for the two cookie endpoints, /auth/refresh and /auth/logout
 * (spec 11.1): the Origin header must equal APP_URL's origin, and the request
 * must carry `X-Requested-With: skimr`. A cross-site form can set neither.
 */
final class CsrfGuard
{
    private readonly string $expectedOrigin;

    public function __construct(string $appUrl)
    {
        $parts = parse_url($appUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('APP_URL must be an absolute URL.');
        }
        $this->expectedOrigin = strtolower($parts['scheme'] . '://' . $parts['host'])
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /** @throws ForbiddenException */
    public function assert(ServerRequestInterface $request): void
    {
        $origin = strtolower(rtrim($request->getHeaderLine('Origin'), '/'));
        if (
            $request->getHeaderLine('X-Requested-With') !== 'skimr'
            || $origin === ''
            || !hash_equals($this->expectedOrigin, $origin)
        ) {
            throw new ForbiddenException();
        }
    }
}
