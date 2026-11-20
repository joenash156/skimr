<?php

declare(strict_types=1);

namespace App\Auth\Token;

use App\Shared\Clock\Clock;
use App\Shared\Exception\InvalidTokenException;
use Doctrine\DBAL\Connection;

/**
 * One-time tokens sent in emailed links (user_tokens).
 *
 *     $raw = $tokens->issue($user->id, TokenType::ADMIN_INVITE, 86400);  // put $raw in the link
 *     $used = $tokens->consume($raw, TokenType::ADMIN_INVITE);           // when the link is opened
 *
 * Only the SHA-256 of the token is stored, so a database leak can't be used
 * to take over accounts. Issuing a new token cancels the user's earlier
 * unused tokens of the same type, so only the latest link works.
 */
final class UserTokenService
{
    private const MAX_TTL = 7 * 86400;

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param string $type one of the TokenType constants
     * @param int $ttlSeconds how long the link works
     * @param array<string, mixed>|null $payload extra data returned by consume(), e.g. ['email' => $new]
     *
     * @return string the raw token (64 hex characters). Send it once in the email; never log or store it.
     */
    public function issue(int $userId, string $type, int $ttlSeconds, ?array $payload = null): string
    {
        self::assertType($type);
        if ($ttlSeconds < 60 || $ttlSeconds > self::MAX_TTL) {
            throw new \InvalidArgumentException('Token TTL must be between 60 seconds and 7 days.');
        }

        $raw = bin2hex(random_bytes(32));
        $now = $this->clock->now();

        $this->db->transactional(function (Connection $db) use ($userId, $type, $ttlSeconds, $payload, $raw, $now): void {
            $this->revokeWith($db, $userId, $type, $now);
            $db->insert('user_tokens', [
                'user_id' => $userId,
                'type' => $type,
                'token_hash' => hash('sha256', $raw),
                'payload' => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'expires_at' => $now->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s.v'),
                'created_at' => $now->format('Y-m-d H:i:s.v'),
            ]);
        });

        return $raw;
    }

    /**
     * Marks the token used and returns who it belongs to. Unknown, expired,
     * already-used and wrong-type tokens all throw the same 400 INVALID_TOKEN.
     *
     * @throws InvalidTokenException
     */
    public function consume(string $rawToken, string $type): ConsumedToken
    {
        self::assertType($type);
        if (!preg_match('/^[0-9a-f]{64}$/', $rawToken)) {
            throw new InvalidTokenException();
        }

        $hash = hash('sha256', $rawToken);
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');

        // One atomic UPDATE: two requests racing with the same link can't both succeed.
        $claimed = $this->db->executeStatement(
            'UPDATE user_tokens SET used_at = ?
             WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > ?',
            [$now, $hash, $type, $now],
        );
        if ($claimed !== 1) {
            throw new InvalidTokenException();
        }

        $row = $this->db->fetchAssociative(
            'SELECT user_id, payload FROM user_tokens WHERE token_hash = ?',
            [$hash],
        );
        if ($row === false) {
            throw new InvalidTokenException();
        }

        $payload = is_string($row['payload']) ? json_decode($row['payload'], true) : [];

        return new ConsumedToken((int) $row['user_id'], $type, is_array($payload) ? $payload : []);
    }

    /** Cancels every unused token of this type for the user (e.g. after "resend invite"). */
    public function revoke(int $userId, string $type): void
    {
        self::assertType($type);
        $this->revokeWith($this->db, $userId, $type, $this->clock->now());
    }

    /** Deletes tokens that expired before $before. Returns the number of rows removed. */
    public function prune(\DateTimeImmutable $before): int
    {
        return (int) $this->db->executeStatement(
            'DELETE FROM user_tokens WHERE expires_at < ?',
            [$before->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v')],
        );
    }

    private function revokeWith(Connection $db, int $userId, string $type, \DateTimeImmutable $now): void
    {
        $db->executeStatement(
            'UPDATE user_tokens SET used_at = ? WHERE user_id = ? AND type = ? AND used_at IS NULL',
            [$now->format('Y-m-d H:i:s.v'), $userId, $type],
        );
    }

    private static function assertType(string $type): void
    {
        if (!in_array($type, TokenType::ALL, true)) {
            throw new \InvalidArgumentException('Unknown token type: ' . $type);
        }
    }
}
