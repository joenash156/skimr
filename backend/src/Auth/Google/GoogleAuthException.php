<?php

declare(strict_types=1);

namespace App\Auth\Google;

/**
 * Google sign-in failed. The message is for the log only; the browser is
 * simply sent to /app/login?error=google.
 */
final class GoogleAuthException extends \RuntimeException
{
}
