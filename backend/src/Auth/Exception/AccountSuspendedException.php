<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Shared\Exception\HttpException;

/**
 * 403 ACCOUNT_SUSPENDED: the right password, but an admin suspended the
 * account. Only shown after the password checks out, so it reveals nothing
 * to someone who doesn't know the password.
 */
final class AccountSuspendedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(403, 'ACCOUNT_SUSPENDED', 'auth.errors.account_suspended');
    }
}
