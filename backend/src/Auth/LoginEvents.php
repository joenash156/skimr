<?php

declare(strict_types=1);

namespace App\Auth;

use App\Shared\Clock\Clock;
use App\Shared\Http\ClientIp;
use Doctrine\DBAL\Connection;

/**
 * The login_events table: every password login attempt, used for the
 * lockout count (spec 5.2) and the new-device email (spec 15.2).
 * The user agent is stored only as a SHA-256 hash.
 */
final class LoginEvents
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function record(?int $userId, ?string $ip, string $userAgent, bool $success): void
    {
        $this->db->insert('login_events', [
            'user_id' => $userId,
            'ip' => ClientIp::toBinary($ip),
            'ua_hash' => self::uaHash($userAgent),
            'success' => $success ? 1 : 0,
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * Failed attempts in the last $windowSeconds that came after the user's
     * most recent successful login.
     */
    public function recentFailures(int $userId, int $windowSeconds): int
    {
        $since = $this->clock->now()->modify('-' . $windowSeconds . ' seconds')->format('Y-m-d H:i:s.v');

        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM login_events
             WHERE user_id = ? AND success = 0 AND created_at > ?
               AND created_at > COALESCE((SELECT MAX(created_at) FROM login_events WHERE user_id = ? AND success = 1), ?)',
            [$userId, $since, $userId, '1970-01-01 00:00:00.000'],
        );
    }

    public function hasSucceededBefore(int $userId): bool
    {
        return (bool) $this->db->fetchOne('SELECT 1 FROM login_events WHERE user_id = ? AND success = 1 LIMIT 1', [$userId]);
    }

    /** True when this user has logged in successfully before from the same IP and browser. */
    public function isKnownDevice(int $userId, ?string $ip, string $userAgent): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM login_events WHERE user_id = ? AND success = 1 AND ua_hash = ? AND ip <=> ? LIMIT 1',
            [$userId, self::uaHash($userAgent), ClientIp::toBinary($ip)],
        );
    }

    private static function uaHash(string $userAgent): string
    {
        return hash('sha256', mb_substr($userAgent, 0, 512));
    }
}
