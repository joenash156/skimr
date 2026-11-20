<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Shared\Exception\HttpException;

/** 429 ACCOUNT_LOCKED with Retry-After: 5 failed logins lock the account for 15 minutes (spec 5.2). */
final class AccountLockedException extends HttpException
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct(429, 'ACCOUNT_LOCKED', 'auth.errors.account_locked', [
            'minutes' => (int) ceil(max(1, $retryAfterSeconds) / 60),
        ]);
    }

    public function headers(): array
    {
        return ['Retry-After' => (string) max(1, $this->retryAfterSeconds)];
    }
}
