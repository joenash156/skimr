<?php

declare(strict_types=1);

namespace App\Shared\Clock;

/** Test clock: returns a fixed time that only moves when advance() is called. */
final class FrozenClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string|\DateTimeImmutable $now = '2026-01-01 00:00:00')
    {
        $utc = new \DateTimeZone('UTC');
        $this->now = is_string($now)
            ? new \DateTimeImmutable($now, $utc)
            : $now->setTimezone($utc);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
