<?php

declare(strict_types=1);

namespace App\Auth\Token;

/** The kinds of one-time emailed tokens (user_tokens.type). */
final class TokenType
{
    /** Sign-up email verification, valid 24 hours. */
    public const VERIFY_EMAIL = 'verify_email';

    /** Forgot-password link, valid 60 minutes. */
    public const RESET_PASSWORD = 'reset_password';

    /** Admin invitation (P2), valid 24 hours. */
    public const ADMIN_INVITE = 'admin_invite';

    /** Confirm a new email address (B2); the payload holds the new address. */
    public const CHANGE_EMAIL = 'change_email';

    public const ALL = [self::VERIFY_EMAIL, self::RESET_PASSWORD, self::ADMIN_INVITE, self::CHANGE_EMAIL];

    private function __construct()
    {
    }
}
