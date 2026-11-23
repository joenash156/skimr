<?php

declare(strict_types=1);

namespace App\Mail;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class EmailBudgetRepository implements EmailBudget
{
    private const SETTING_KEY = 'mail.daily_email_count';
    private const OPTIONAL_LIMIT = 80;

    public function __construct(private readonly Connection $db)
    {
    }

    public function reserve(\DateTimeImmutable $now, bool $optional): ?string
    {
        $day = $now->format('Y-m-d');
        $timestamp = $now->format('Y-m-d H:i:s.v');

        return $this->db->transactional(function (Connection $db) use ($day, $timestamp, $optional): ?string {
            $db->executeStatement(
                'INSERT INTO settings (`key`, value, updated_by, updated_at) VALUES (?, ?, NULL, ?)
                 ON DUPLICATE KEY UPDATE value = value',
                [
                    self::SETTING_KEY,
                    json_encode(['day' => $day, 'count' => 0], JSON_THROW_ON_ERROR),
                    $timestamp,
                ],
            );

            $counter = $this->readCounter($db);
            $count = $counter['day'] === $day ? $counter['count'] : 0;
            if ($optional && $count >= self::OPTIONAL_LIMIT) {
                return null;
            }

            $db->executeStatement(
                'UPDATE settings SET value = ?, updated_by = NULL, updated_at = ? WHERE `key` = ?',
                [
                    json_encode(['day' => $day, 'count' => $count + 1], JSON_THROW_ON_ERROR),
                    $timestamp,
                    self::SETTING_KEY,
                ],
            );

            return $day;
        });
    }

    public function release(string $day, \DateTimeImmutable $now): void
    {
        $timestamp = $now->format('Y-m-d H:i:s.v');

        $this->db->transactional(function (Connection $db) use ($day, $timestamp): void {
            $counter = $this->readCounter($db);
            if ($counter['day'] !== $day || $counter['count'] === 0) {
                return;
            }

            $db->executeStatement(
                'UPDATE settings SET value = ?, updated_by = NULL, updated_at = ? WHERE `key` = ?',
                [
                    json_encode(['day' => $day, 'count' => $counter['count'] - 1], JSON_THROW_ON_ERROR),
                    $timestamp,
                    self::SETTING_KEY,
                ],
            );
        });
    }

    /**
     * @return array{day: string, count: int}
     */
    private function readCounter(Connection $db): array
    {
        $raw = $db->fetchOne('SELECT value FROM settings WHERE `key` = ? FOR UPDATE', [self::SETTING_KEY]);
        if (!is_string($raw)) {
            throw new RuntimeException('The daily email counter could not be read.');
        }

        $value = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (
            !is_array($value)
            || !is_string($value['day'] ?? null)
            || !is_int($value['count'] ?? null)
            || $value['count'] < 0
        ) {
            throw new RuntimeException('The daily email counter has an invalid value.');
        }

        return ['day' => $value['day'], 'count' => $value['count']];
    }
}
