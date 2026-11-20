<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * 403 EMAIL_NOT_VERIFIED: a signed-in user with status pending_verification
 * called an endpoint other than GET /me or /auth/* (spec 5.2). The app shows
 * the "Verify your email" screen when it sees this code.
 */
final class EmailNotVerifiedException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(403, 'EMAIL_NOT_VERIFIED', 'errors.email_not_verified', [], $previous);
    }
}
