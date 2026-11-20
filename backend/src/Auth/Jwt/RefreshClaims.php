<?php

declare(strict_types=1);

namespace App\Auth\Jwt;

/** The verified claims of a refresh token (spec 11.1). */
final class RefreshClaims
{
    public function __construct(
        public readonly string $userUuid,
        public readonly string $jti,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
    ) {
    }
}
