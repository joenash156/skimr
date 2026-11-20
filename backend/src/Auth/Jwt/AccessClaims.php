<?php

declare(strict_types=1);

namespace App\Auth\Jwt;

/** The verified claims of an access token (spec 11.1). */
final class AccessClaims
{
    public function __construct(
        public readonly string $userUuid,
        public readonly string $role,
        public readonly int $tokenVersion,
        public readonly string $jti,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
    ) {
    }
}
