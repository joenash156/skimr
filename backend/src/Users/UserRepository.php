<?php

declare(strict_types=1);

namespace App\Users;

use App\Shared\Clock\Clock;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

/**
 * Reads and writes users (spec 9.1).
 *
 * Deleted users (status = deleted) are never returned by the finders: to the
 * rest of the app they don't exist.
 */
final class UserRepository
{
    /** Columns update() may change. Anything else is a programming error. */
    private const UPDATABLE = [
        'role', 'status', 'first_name', 'last_name', 'other_names', 'email', 'email_verified_at',
        'phone', 'alt_phone', 'institution', 'discipline', 'avatar_public_id', 'password_hash',
        'google_sub', 'locale', 'theme', 'citation_style', 'notification_prefs',
        'failed_login_count', 'locked_until', 'last_login_at', 'last_login_ip', 'deleted_at',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function findById(int $id): ?User
    {
        return $this->findOneBy('id', $id);
    }

    public function findByUuid(string $uuid): ?User
    {
        if (!preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
            return null;
        }

        return $this->findOneBy('uuid', $uuid);
    }

    /** The email is matched lowercased, the way it is stored. */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy('email', self::normalizeEmail($email));
    }

    /** The account linked to this Google id ("sub"), if any. */
    public function findByGoogleSub(string $sub): ?User
    {
        return $sub === '' ? null : $this->findOneBy('google_sub', $sub);
    }

    /** True if any account, including a deleted one, holds this Google id. */
    public function googleSubExists(string $sub): bool
    {
        return (bool) $this->db->fetchOne('SELECT 1 FROM users WHERE google_sub = ? LIMIT 1', [$sub]);
    }

    /** True if any account, including a deleted one, holds this email (the column is UNIQUE). */
    public function emailExists(string $email): bool
    {
        return (bool) $this->db->fetchOne('SELECT 1 FROM users WHERE email = ? LIMIT 1', [self::normalizeEmail($email)]);
    }

    /** Used to stop the last active super admin from being demoted, suspended or deleted. */
    public function countActiveSuperAdmins(): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM users WHERE role = ? AND status = ?',
            [User::ROLE_SUPER_ADMIN, User::STATUS_ACTIVE],
        );
    }

    /**
     * Inserts a user and returns it. Required: first_name, last_name, email.
     * The email is lowercased; uuid and timestamps are generated.
     *
     * @param array<string, mixed> $columns
     */
    public function create(array $columns): User
    {
        foreach (['first_name', 'last_name', 'email'] as $required) {
            if (!isset($columns[$required]) || $columns[$required] === '') {
                throw new \InvalidArgumentException('Missing user column: ' . $required);
            }
        }
        $unknown = array_diff(array_keys($columns), [...self::UPDATABLE, 'invited_by']);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown user columns: ' . implode(', ', $unknown));
        }

        $now = $this->now();
        $uuid = Uuid::uuid4()->toString();
        $this->db->insert('users', [
            ...self::toRow($columns),
            'uuid' => $uuid,
            'email' => self::normalizeEmail((string) $columns['email']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByUuidIncludingDeleted($uuid)
            ?? throw new \RuntimeException('User vanished right after insert.');
    }

    /**
     * Updates the given columns (see UPDATABLE). DateTimeImmutable values are
     * stored as UTC DATETIME(3); arrays as JSON.
     *
     * @param array<string, mixed> $columns
     */
    public function update(int $id, array $columns): void
    {
        $unknown = array_diff(array_keys($columns), self::UPDATABLE);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Not updatable: ' . implode(', ', $unknown));
        }
        if (isset($columns['email']) && is_string($columns['email'])) {
            $columns['email'] = self::normalizeEmail($columns['email']);
        }

        $this->db->update('users', ['updated_at' => $this->now()] + self::toRow($columns), ['id' => $id]);
    }

    /**
     * DateTimeImmutable values become UTC DATETIME(3) strings; arrays become JSON.
     *
     * @param array<string, mixed> $columns
     * @return array<string, mixed>
     */
    private static function toRow(array $columns): array
    {
        $row = [];
        foreach ($columns as $column => $value) {
            $row[$column] = match (true) {
                $value instanceof \DateTimeImmutable => self::format($value),
                is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
                default => $value,
            };
        }

        return $row;
    }

    /**
     * Starts a new session (login, invite accept, Google): stores the refresh
     * token hash and forgets the previous one. One active session per user (spec 9.1 note).
     */
    public function startSession(int $id, string $refreshHash, \DateTimeImmutable $expiresAt): void
    {
        $this->db->update('users', [
            'refresh_token_hash' => $refreshHash,
            'refresh_token_prev_hash' => null,
            'refresh_token_expires_at' => self::format($expiresAt),
            'updated_at' => $this->now(),
        ], ['id' => $id]);
    }

    /**
     * Rotation (spec 11.1): swaps the current hash for a new one and keeps the
     * old one in refresh_token_prev_hash. Atomic: returns false if the current
     * hash changed in the meantime (another refresh won the race).
     */
    public function rotateRefreshToken(int $id, string $currentHash, string $newHash, \DateTimeImmutable $expiresAt): bool
    {
        return $this->db->executeStatement(
            'UPDATE users
             SET refresh_token_prev_hash = refresh_token_hash, refresh_token_hash = ?, refresh_token_expires_at = ?, updated_at = ?
             WHERE id = ? AND refresh_token_hash = ?',
            [$newHash, self::format($expiresAt), $this->now(), $id, $currentHash],
        ) === 1;
    }

    /** Logout: forgets the refresh token. Access tokens run out on their own within 15 minutes. */
    public function endSession(int $id): void
    {
        $this->db->update('users', [
            'refresh_token_hash' => null,
            'refresh_token_prev_hash' => null,
            'refresh_token_expires_at' => null,
            'updated_at' => $this->now(),
        ], ['id' => $id]);
    }

    /** Sign out everywhere (spec 11.1): forget the refresh token AND cancel every access token. */
    public function revokeAllSessions(int $id): void
    {
        $this->db->executeStatement(
            'UPDATE users
             SET refresh_token_hash = NULL, refresh_token_prev_hash = NULL, refresh_token_expires_at = NULL,
                 token_version = token_version + 1, updated_at = ?
             WHERE id = ?',
            [$this->now(), $id],
        );
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function findByUuidIncludingDeleted(string $uuid): ?User
    {
        $row = $this->db->fetchAssociative('SELECT * FROM users WHERE uuid = ?', [$uuid]);

        return $row === false ? null : User::fromRow($row);
    }

    /** @param 'id'|'uuid'|'email'|'google_sub' $column */
    private function findOneBy(string $column, int|string $value): ?User
    {
        $row = $this->db->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->where($column . ' = :value')
            ->andWhere('status <> :deleted')
            ->setParameter('value', $value)
            ->setParameter('deleted', User::STATUS_DELETED)
            ->setMaxResults(1)
            ->fetchAssociative();

        return $row === false ? null : User::fromRow($row);
    }

    private function now(): string
    {
        return self::format($this->clock->now());
    }

    private static function format(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
