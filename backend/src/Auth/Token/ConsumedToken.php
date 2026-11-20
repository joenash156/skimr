<?php

declare(strict_types=1);

namespace App\Auth\Token;

/** What UserTokenService::consume() hands back after a token has been used up. */
final class ConsumedToken
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly int $userId,
        public readonly string $type,
        public readonly array $payload,
    ) {
    }
}
