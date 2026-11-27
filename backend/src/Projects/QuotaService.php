<?php

declare(strict_types=1);

namespace App\Projects;

use App\Shared\Clock\Clock;
use App\Shared\Exception\QuotaExceededException;
use Doctrine\DBAL\Connection;

final class QuotaService
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function assertSourceAllowed(int $userId, int $projectId): void
    {
        $projectCount = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM sources WHERE project_id = ?',
            [$projectId],
        );
        if ($projectCount >= 30) {
            throw new QuotaExceededException(86400);
        }

        $userCount = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM sources WHERE added_by = ?',
            [$userId],
        );
        if ($userCount >= 100) {
            throw new QuotaExceededException(86400);
        }
    }

    public function assertLinkAllowed(int $userId): void
    {
        $day = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        $count = (int) $this->db->fetchOne(
            'SELECT links_added FROM usage_counters WHERE user_id = ? AND day = ?',
            [$userId, $day],
        );
        if ($count >= 30) {
            throw new QuotaExceededException(3600);
        }
    }

    public function increment(int $userId, string $column, int $amount = 1): void
    {
        if (!in_array($column, ['ai_requests', 'chat_messages', 'links_added', 'ocr_fallback_pages', 'bytes_uploaded'], true)) {
            throw new \InvalidArgumentException('Unknown usage counter.');
        }

        $day = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        $this->db->executeStatement(
            'INSERT INTO usage_counters (user_id, day, ' . $column . ')
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE ' . $column . ' = ' . $column . ' + VALUES(' . $column . ')',
            [$userId, $day, $amount],
        );
    }
}
