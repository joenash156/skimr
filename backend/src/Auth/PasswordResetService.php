<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Password\PasswordHasher;
use App\Auth\Token\TokenType;
use App\Auth\Token\UserTokenService;
use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Shared\Clock\Clock;
use App\Shared\Exception\InvalidTokenException;
use App\Shared\Mail\Mailer;
use App\Users\User;
use App\Users\UserRepository;

/**
 * Forgot / reset password (spec 5.2): a 60-minute single-use link; a reset
 * signs the user out everywhere.
 */
final class PasswordResetService
{
    public const TTL = 3600;

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserTokenService $tokens,
        private readonly PasswordHasher $hasher,
        private readonly AuthService $auth,
        private readonly AuthLinks $links,
        private readonly AuditLogger $audit,
        private readonly Mailer $mailer,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Emails a reset link if the account exists and may sign in. Returns
     * nothing either way: the caller always answers the same (spec 5.2).
     */
    public function requestReset(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !($user->isActive() || $user->isPendingVerification())) {
            return;
        }

        $token = $this->tokens->issue($user->id, TokenType::RESET_PASSWORD, self::TTL);
        $this->mailer->send('reset_password', $user->email, $user->locale, [
            'first_name' => $user->firstName,
            'link' => $this->links->resetPassword($token),
            'expires_minutes' => intdiv(self::TTL, 60),
        ]);
    }

    /**
     * Sets the new password (already checked against PasswordPolicy), clears
     * any lockout and signs out every session.
     *
     * @throws InvalidTokenException
     */
    public function reset(string $token, string $newPassword, ?string $ip = null): User
    {
        $consumed = $this->tokens->consume($token, TokenType::RESET_PASSWORD);
        $user = $this->users->findById($consumed->userId) ?? throw new InvalidTokenException();
        if (!($user->isActive() || $user->isPendingVerification())) {
            throw new InvalidTokenException();
        }

        $now = $this->clock->now();
        $changes = [
            'password_hash' => $this->hasher->hash($newPassword),
            'failed_login_count' => 0,
            'locked_until' => null,
        ];
        if ($user->isPendingVerification()) {
            // The link reached their inbox, which proves they own the address.
            $changes['status'] = User::STATUS_ACTIVE;
            $changes['email_verified_at'] = $user->emailVerifiedAt ?? $now;
        }
        $this->users->update($user->id, $changes);
        $this->auth->signOutEverywhere($user);

        $this->audit->log($user, AuditEvent::AUTH_PASSWORD_RESET, 'user', $user->uuid, null, [], $ip);
        $this->mailer->send('password_changed', $user->email, $user->locale, [
            'first_name' => $user->firstName,
            'at' => $now->format(DATE_ATOM),
        ]);

        return $this->users->findById($user->id) ?? $user;
    }
}
