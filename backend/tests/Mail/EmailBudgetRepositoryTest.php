<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Mail\EmailBudgetRepository;
use App\Tests\DatabaseTestCase;
use DateTimeImmutable;
use DateTimeZone;

final class EmailBudgetRepositoryTest extends DatabaseTestCase
{
    public function testOptionalBudgetIsAtomicPerUtcDayAndMandatoryMailCanExceedIt(): void
    {
        $budget = new EmailBudgetRepository($this->db());
        $today = new DateTimeImmutable('2026-11-20 10:00:00', new DateTimeZone('UTC'));

        for ($i = 0; $i < 80; $i++) {
            self::assertSame('2026-11-20', $budget->reserve($today, true));
        }
        self::assertNull($budget->reserve($today, true));
        self::assertSame('2026-11-20', $budget->reserve($today, false));

        $budget->release('2026-11-20', $today);
        self::assertNull($budget->reserve($today, true));

        $tomorrow = $today->modify('+1 day');
        self::assertSame('2026-11-21', $budget->reserve($tomorrow, true));
    }

    public function testReleasingFailedOptionalEmailFreesItsReservation(): void
    {
        $budget = new EmailBudgetRepository($this->db());
        $today = new DateTimeImmutable('2026-11-20 10:00:00', new DateTimeZone('UTC'));

        for ($i = 0; $i < 80; $i++) {
            self::assertSame('2026-11-20', $budget->reserve($today, true));
        }
        $budget->release('2026-11-20', $today);

        self::assertSame('2026-11-20', $budget->reserve($today, true));
    }
}
