<?php

declare(strict_types=1);

namespace App\Users;

use App\Auth\AuthLinks;
use App\Auth\AuthService;
use App\Auth\Exception\InvalidCredentialsException;
use App\Auth\Password\PasswordHasher;
use App\Auth\Token\TokenType;
use App\Auth\Token\UserTokenService;
use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Security\RateLimit\RateLimit;
use App\Security\RateLimit\RateLimiter;
use App\Shared\Clock\Clock;
use App\Shared\Exception\ConflictException;
use App\Shared\Exception\InvalidTokenException;
use App\Shared\Exception\QuotaExceededException;
use App\Shared\Mail\Mailer;

/**
 * The signed-in user's own account (P1 brief B2 task 2): password change,
 * email change, deletion. Every one of these asks for the current password
 * again, with its own brute-force limit (5 wrong tries per 15 minutes).
 */
final class AccountService
{
    public const EMAIL_CHANGE_TTL = 86400;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly AuthService $auth,
        private readonly UserTokenService $tokens,
        private readonly AuthLinks $links,
        private readonly RateLimiter $rateLimiter,
        private readonly AuditLogger $audit,
        private readonly Mailer $mailer,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Re-checks the current password. Google-only accounts have none: they
     * set one through "forgot password" first, which proves email ownership.
     *
     * @throws InvalidCredentialsException|QuotaExceededException
     */
    public function confirmPassword(User $user, string $password): void
    {
        $subject = 'reauth:' . $user->uuid;
        if ($this->rateLimiter->isFull(RateLimit::login(), $subject)) {
            throw QuotaExceededException::rateLimited($this->rateLimiter->retryAfter(RateLimit::login()));
        }
        if (!$this->hasher->verify($password, $user->passwordHash)) {
            $this->rateLimiter->hit(RateLimit::login(), $subject);
            throw new InvalidCredentialsException();
        }
    }

    /** New password already checked against PasswordPolicy. Signs out everywhere (spec 11.1). */
    public function changePassword(User $user, string $currentPassword, string $newPassword, ?string $ip): void
    {
        $this->confirmPassword($user, $currentPassword);

        $this->users->update($user->id, ['password_hash' => $this->hasher->hash($newPassword)]);
        $this->auth->signOutEverywhere($user);
        $this->audit->log($user, AuditEvent::USER_PASSWORD_CHANGED, 'user', $user->uuid, null, [], $ip);
        $this->mailer->send('password_changed', $user->email, $user->locale, [
            'first_name' => $user->firstName,
            'at' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }

    /**
     * Step 1 of an email change: a 24-hour confirmation link goes to the new
     * address, a heads-up to the old one. Nothing changes until the link is opened.
     *
     * @throws ConflictException when another account already uses the new address
     */
    public function requestEmailChange(User $user, string $newEmail, string $password): void
    {
        $this->confirmPassword($user, $password);

        $newEmail = UserRepository::normalizeEmail($newEmail);
        if ($newEmail === $user->email) {
            return;
        }
        if ($this->users->emailExists($newEmail)) {
            throw new ConflictException('auth.errors.email_taken');
        }

        $token = $this->tokens->issue($user->id, TokenType::CHANGE_EMAIL, self::EMAIL_CHANGE_TTL, ['email' => $newEmail]);
        $this->mailer->send('verify_email', $newEmail, $user->locale, [
            'first_name' => $user->firstName,
            'link' => $this->links->verifyEmail($token),
            'expires_hours' => intdiv(self::EMAIL_CHANGE_TTL, 3600),
        ]);
        $this->mailer->send('email_change_requested', $user->email, $user->locale, [
            'first_name' => $user->firstName,
            'new_email' => $newEmail,
            'at' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }

    /**
     * Step 2: the link from the new inbox was opened (via POST /auth/verify-email).
     *
     * @param array<string, mixed> $payload the token payload: ['email' => new address]
     *
     * @throws InvalidTokenException|ConflictException
     */
    public function confirmEmailChange(int $userId, array $payload): User
    {
        $user = $this->users->findById($userId) ?? throw new InvalidTokenException();
        $newEmail = $payload['email'] ?? null;
        if (!is_string($newEmail) || $newEmail === '') {
            throw new InvalidTokenException();
        }
        if ($this->users->emailExists($newEmail)) {
            // Someone registered it between the request and the click.
            throw new ConflictException('auth.errors.email_taken');
        }

        $oldEmail = $user->email;
        $this->users->update($user->id, ['email' => $newEmail, 'email_verified_at' => $this->clock->now()]);
        $this->audit->log($user, AuditEvent::USER_EMAIL_CHANGED, 'user', $user->uuid, null, [
            // Domains only: enough to investigate, without keeping full addresses in the trail.
            'old_domain' => substr((string) strrchr($oldEmail, '@'), 1),
            'new_domain' => substr((string) strrchr($newEmail, '@'), 1),
        ]);
        $this->mailer->send('email_changed', $oldEmail, $user->locale, [
            'first_name' => $user->firstName,
            'new_email' => $newEmail,
            'at' => $this->clock->now()->format(DATE_ATOM),
        ]);

        return $this->users->findById($user->id) ?? $user;
    }

    /**
     * Soft delete (spec: 30 days, then P2's purge job removes the data).
     * The account disappears immediately and every session ends.
     */
    public function deleteAccount(User $user, string $password, ?string $ip): void
    {
        $this->confirmPassword($user, $password);

        $this->users->update($user->id, [
            'status' => User::STATUS_DELETED,
            'deleted_at' => $this->clock->now(),
        ]);
        $this->auth->signOutEverywhere($user);
        $this->audit->log($user, AuditEvent::USER_ACCOUNT_DELETED, 'user', $user->uuid, null, [], $ip);
    }
}
