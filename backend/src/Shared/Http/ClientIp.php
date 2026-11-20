<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The client's IP address, for rate limits, login_events and audit_logs.
 *
 * Only REMOTE_ADDR is trusted. X-Forwarded-For is set by the client and
 * would let anyone dodge per-IP rate limits, so it is ignored.
 */
final class ClientIp
{
    private function __construct()
    {
    }

    public static function of(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    /** Packed form for VARBINARY(16) columns (inet_pton), or null. */
    public static function toBinary(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }
        $packed = @inet_pton($ip);

        return $packed === false ? null : $packed;
    }
}
