<?php

declare(strict_types=1);

namespace App\Security\RateLimit;

use App\Shared\Clock\Clock;
use App\Shared\Exception\QuotaExceededException;
use Doctrine\DBAL\Connection;

/**
 * Fixed-window rate limiter stored in the rate_limits table (spec 11.4).
 *
 * The subject says who is being counted. Prefix it so buckets never mix:
 * 'ip:203.0.113.5', 'user:<uuid>', 'email:a@b.c|ip:203.0.113.5'. Subjects are
 * stored only as a SHA-256 hash, so emails and IPs never sit in this table.
 *
 *     $rateLimiter->consume(RateLimit::register(), 'ip:' . $ip); // throws 429 when full
 */
final class RateLimiter
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Counts one hit and throws 429 RATE_LIMITED (with Retry-After) once the
     * bucket is over its limit.
     *
     * @throws QuotaExceededException
     */
    public function consume(RateLimit $limit, string $subject): void
    {
        $hits = $this->hit($limit, $subject);
        if ($hits > $limit->limit) {
            throw QuotaExceededException::rateLimited($this->retryAfter($limit));
        }
    }

    /** Counts one hit and returns the total for the current window. */
    public function hit(RateLimit $limit, string $subject): int
    {
        $key = $this->key($limit, $subject);
        $window = $this->windowStart($limit);

        $this->db->executeStatement(
            'INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1',
            [$key, $window],
        );

        return $this->count($key, $window);
    }

    /** Hits so far in the current window, without counting a new one. */
    public function hits(RateLimit $limit, string $subject): int
    {
        return $this->count($this->key($limit, $subject), $this->windowStart($limit));
    }

    /** True when the bucket is already at its limit (the next hit would be refused). */
    public function isFull(RateLimit $limit, string $subject): bool
    {
        return $this->hits($limit, $subject) >= $limit->limit;
    }

    /** Forgets the current window, e.g. the login bucket after a successful login. */
    public function clear(RateLimit $limit, string $subject): void
    {
        $this->db->executeStatement(
            'DELETE FROM rate_limits WHERE bucket_key = ?',
            [$this->key($limit, $subject)],
        );
    }

    /** Seconds until the current window ends. */
    public function retryAfter(RateLimit $limit): int
    {
        $now = $this->clock->now()->getTimestamp();
        $windowEnd = intdiv($now, $limit->windowSeconds) * $limit->windowSeconds + $limit->windowSeconds;

        return max(1, $windowEnd - $now);
    }

    /** Deletes windows that started before $before. Returns the number of rows removed. */
    public function prune(\DateTimeImmutable $before): int
    {
        return (int) $this->db->executeStatement(
            'DELETE FROM rate_limits WHERE window_start < ?',
            [$before->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v')],
        );
    }

    private function count(string $key, string $window): int
    {
        return (int) $this->db->fetchOne(
            'SELECT hits FROM rate_limits WHERE bucket_key = ? AND window_start = ?',
            [$key, $window],
        );
    }

    private function key(RateLimit $limit, string $subject): string
    {
        return hash('sha256', $limit->name . '|' . $subject);
    }

    private function windowStart(RateLimit $limit): string
    {
        $now = $this->clock->now()->getTimestamp();
        $start = intdiv($now, $limit->windowSeconds) * $limit->windowSeconds;

        return (new \DateTimeImmutable('@' . $start))->format('Y-m-d H:i:s.v');
    }
}
