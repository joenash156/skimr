<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Shared\Exception\HttpException;

/** 401 INVALID_CREDENTIALS: the same answer for "no such email" and "wrong password" (spec 11.2). */
final class InvalidCredentialsException extends HttpException
{
    public function __construct()
    {
        parent::__construct(401, 'INVALID_CREDENTIALS', 'auth.errors.invalid_credentials');
    }
}
