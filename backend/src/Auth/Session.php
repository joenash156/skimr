<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Jwt\IssuedToken;
use App\Users\User;

/** A freshly started or refreshed session: the access token for the body, the refresh token for the cookie. */
final class Session
{
    public function __construct(
        public readonly User $user,
        public readonly IssuedToken $accessToken,
        public readonly IssuedToken $refreshToken,
    ) {
    }
}
