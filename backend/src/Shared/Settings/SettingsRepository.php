<?php

declare(strict_types=1);

namespace App\Shared\Settings;

use App\Shared\Clock\Clock;
use Doctrine\DBAL\Connection;

/**
 * The settings table: app-wide values the super admin controls (spec 5.4),
 * stored as JSON under a key. P2's /admin/settings (B4) writes through set();
 * anything may read through get().
 */
final class SettingsRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /** The decoded JSON value, or $default when the key isn't set. */
    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->db->fetchOne('SELECT value FROM settings WHERE `key` = ?', [$key]);
        if (!is_string($raw)) {
            return $default;
        }

        return json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    }

    /** Creates or replaces a setting. $updatedBy is the admin's users.id (null for system changes). */
    public function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        $this->db->executeStatement(
            'INSERT INTO settings (`key`, value, updated_by, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
            [
                $key,
                json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $updatedBy,
                $this->clock->now()->format('Y-m-d H:i:s.v'),
            ],
        );
    }
}
