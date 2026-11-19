<?php

declare(strict_types=1);

namespace App\Shared\Clock;

/**
 * Inject this instead of calling new DateTimeImmutable() so time-dependent
 * logic (token expiry, lockouts, rate-limit windows) can be tested.
 */
interface Clock
{
    /** Current time, always in UTC (spec 9: timestamps are stored in UTC). */
    public function now(): \DateTimeImmutable;
}
