<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/** 403 TURNSTILE_FAILED: the Cloudflare Turnstile bot check failed (spec 11.2, contract "TurnstileFailed"). */
final class TurnstileFailedException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(403, 'TURNSTILE_FAILED', 'errors.turnstile_failed', [], $previous);
    }
}
