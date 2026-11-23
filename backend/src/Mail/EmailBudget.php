<?php

declare(strict_types=1);

namespace App\Mail;

interface EmailBudget
{
    /**
     * Reserves one send. Returns its UTC date, or null when an optional send
     * would exceed the daily limit.
     */
    public function reserve(\DateTimeImmutable $now, bool $optional): ?string;

    public function release(string $day, \DateTimeImmutable $now): void;
}
