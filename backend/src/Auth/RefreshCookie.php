<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Jwt\IssuedToken;
use App\Shared\Clock\Clock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The refresh-token cookie (spec 11.1):
 *
 *     __Secure-rt=<jwt>; Max-Age=1209600; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict
 *
 * JavaScript can't read it, and the browser only sends it to /api/v1/auth/*.
 * The __Secure- prefix makes browsers refuse it without Secure; browsers
 * treat http://localhost as secure, so local development still works.
 */
final class RefreshCookie
{
    public const NAME = '__Secure-rt';
    public const PATH = '/api/v1/auth';

    public function __construct(private readonly Clock $clock)
    {
    }

    public function set(ResponseInterface $response, IssuedToken $token): ResponseInterface
    {
        $maxAge = max(0, $token->expiresAt->getTimestamp() - $this->clock->now()->getTimestamp());

        return $response->withAddedHeader('Set-Cookie', self::header($token->token, $maxAge));
    }

    public function clear(ResponseInterface $response): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', self::clearHeader());
    }

    public static function read(ServerRequestInterface $request): ?string
    {
        $value = $request->getCookieParams()[self::NAME] ?? null;

        return is_string($value) && $value !== '' && strlen($value) <= 4096 ? $value : null;
    }

    public static function clearHeader(): string
    {
        return self::header('', 0);
    }

    private static function header(string $value, int $maxAge): string
    {
        return sprintf(
            '%s=%s; Max-Age=%d; Path=%s; Secure; HttpOnly; SameSite=Strict',
            self::NAME,
            $value,
            $maxAge,
            self::PATH,
        );
    }
}
