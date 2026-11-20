<?php

declare(strict_types=1);

namespace App\Auth\Jwt;

/** A freshly signed JWT. Store only hash('sha256', $token), never the token itself. */
final class IssuedToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $jti,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    public function hash(): string
    {
        return hash('sha256', $this->token);
    }
}
