<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * 400 INVALID_TOKEN: an emailed link (verify email, reset password, admin
 * invite, change email) is unknown, expired or already used. One answer for
 * all three, so a token can't be probed.
 */
final class InvalidTokenException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(400, 'INVALID_TOKEN', 'errors.invalid_token', [], $previous);
    }
}
