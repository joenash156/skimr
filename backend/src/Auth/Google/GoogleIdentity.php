<?php

declare(strict_types=1);

namespace App\Auth\Google;

/** Who Google says signed in, taken from a verified id_token. */
final class GoogleIdentity
{
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly ?string $givenName,
        public readonly ?string $familyName,
    ) {
    }
}
