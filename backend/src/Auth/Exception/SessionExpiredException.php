<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Auth\RefreshCookie;
use App\Shared\Exception\HttpException;

/**
 * 401 from /auth/refresh that also expires the refresh cookie, so the browser
 * stops sending a dead token.
 *
 * - UNAUTHENTICATED: no cookie, a bad or expired token, or the session ended.
 * - REFRESH_REUSED: an already-rotated token was replayed (spec 11.1 reuse detection).
 */
final class SessionExpiredException extends HttpException
{
    private function __construct(string $code, string $messageKey)
    {
        parent::__construct(401, $code, $messageKey);
    }

    public static function unauthenticated(): self
    {
        return new self('UNAUTHENTICATED', 'errors.unauthenticated');
    }

    public static function reused(): self
    {
        return new self('REFRESH_REUSED', 'auth.errors.refresh_reused');
    }

    public function headers(): array
    {
        return ['Set-Cookie' => RefreshCookie::clearHeader()];
    }
}
