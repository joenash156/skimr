<?php

declare(strict_types=1);

namespace App\Users;

/**
 * A row of the users table (spec 9.1), read-only.
 *
 * AuthMiddleware puts the signed-in user on the request:
 *
 *     $user = $request->getAttribute(RequestAttributes::USER); // App\Users\User
 *
 * Secrets (password_hash, refresh token hashes) are kept here for the auth
 * services only. Never serialise a User straight into a response or a log.
 */
final class User
{
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLES = [self::ROLE_USER, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INVITED = 'invited';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_DELETED = 'deleted';

    /**
     * @param array<string, bool>|null $notificationPrefs null means "all defaults" (spec 15.2)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $role,
        public readonly string $status,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $otherNames,
        public readonly string $email,
        public readonly ?\DateTimeImmutable $emailVerifiedAt,
        public readonly ?string $phone,
        public readonly ?string $altPhone,
        public readonly ?string $institution,
        public readonly ?string $discipline,
        public readonly ?string $avatarPublicId,
        public readonly ?string $passwordHash,
        public readonly ?string $googleSub,
        public readonly string $locale,
        public readonly string $theme,
        public readonly string $citationStyle,
        public readonly ?array $notificationPrefs,
        public readonly ?string $refreshTokenHash,
        public readonly ?string $refreshTokenPrevHash,
        public readonly ?\DateTimeImmutable $refreshTokenExpiresAt,
        public readonly int $tokenVersion,
        public readonly int $failedLoginCount,
        public readonly ?\DateTimeImmutable $lockedUntil,
        public readonly ?\DateTimeImmutable $lastLoginAt,
        public readonly ?int $invitedBy,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** @param array<string, mixed> $row a users row as returned by DBAL */
    public static function fromRow(array $row): self
    {
        $prefs = null;
        if (is_string($row['notification_prefs'] ?? null)) {
            $decoded = json_decode($row['notification_prefs'], true);
            $prefs = is_array($decoded) ? array_map('boolval', $decoded) : null;
        }

        return new self(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            role: (string) $row['role'],
            status: (string) $row['status'],
            firstName: (string) $row['first_name'],
            lastName: (string) $row['last_name'],
            otherNames: self::str($row['other_names'] ?? null),
            email: (string) $row['email'],
            emailVerifiedAt: self::date($row['email_verified_at'] ?? null),
            phone: self::str($row['phone'] ?? null),
            altPhone: self::str($row['alt_phone'] ?? null),
            institution: self::str($row['institution'] ?? null),
            discipline: self::str($row['discipline'] ?? null),
            avatarPublicId: self::str($row['avatar_public_id'] ?? null),
            passwordHash: self::str($row['password_hash'] ?? null),
            googleSub: self::str($row['google_sub'] ?? null),
            locale: (string) $row['locale'],
            theme: (string) $row['theme'],
            citationStyle: (string) $row['citation_style'],
            notificationPrefs: $prefs,
            refreshTokenHash: self::str($row['refresh_token_hash'] ?? null),
            refreshTokenPrevHash: self::str($row['refresh_token_prev_hash'] ?? null),
            refreshTokenExpiresAt: self::date($row['refresh_token_expires_at'] ?? null),
            tokenVersion: (int) $row['token_version'],
            failedLoginCount: (int) $row['failed_login_count'],
            lockedUntil: self::date($row['locked_until'] ?? null),
            lastLoginAt: self::date($row['last_login_at'] ?? null),
            invitedBy: isset($row['invited_by']) ? (int) $row['invited_by'] : null,
            createdAt: self::date($row['created_at']) ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: self::date($row['updated_at']) ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPendingVerification(): bool
    {
        return $this->status === self::STATUS_PENDING_VERIFICATION;
    }

    /** True for admins and super admins. */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isLocked(\DateTimeImmutable $now): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > $now;
    }

    private static function str(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }
}
