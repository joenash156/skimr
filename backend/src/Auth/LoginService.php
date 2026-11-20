<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exception\AccountLockedException;
use App\Auth\Exception\AccountSuspendedException;
use App\Auth\Exception\InvalidCredentialsException;
use App\Auth\Password\PasswordHasher;
use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Shared\Clock\Clock;
use App\Shared\Http\ClientIp;
use App\Shared\Mail\Mailer;
use App\Users\User;
use App\Users\UserRepository;

/**
 * Email + password login (spec 5.2, P1 brief B1 task 8).
 *
 * - Unknown email and wrong password give the same 401 and take the same time.
 * - 5 failures within 15 minutes lock the account for 15 minutes; while locked,
 *   even the right password gets 429 ACCOUNT_LOCKED.
 * - Every attempt on a known account goes into login_events.
 * - A successful login from a new IP + browser sends the "new sign-in" email.
 *
 * Turnstile and the per email+IP rate limit run before this, as route middleware.
 */
final class LoginService
{
    public const MAX_FAILURES = 5;
    public const FAILURE_WINDOW = 900;
    public const LOCK_SECONDS = 900;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly LoginEvents $events,
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
        private readonly Mailer $mailer,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws InvalidCredentialsException|AccountLockedException|AccountSuspendedException
     */
    public function login(string $email, string $password, ?string $ip, string $userAgent): Session
    {
        $now = $this->clock->now();
        $user = $this->users->findByEmail($email);

        if ($user !== null && $user->isLocked($now)) {
            // Not recorded as a failure, so waiting out the lock really resets it.
            throw new AccountLockedException($user->lockedUntil->getTimestamp() - $now->getTimestamp());
        }

        if ($user === null || !$this->hasher->verify($password, $user->passwordHash)) {
            if ($user === null) {
                $this->hasher->verify($password, null); // same bcrypt cost as a real check
            }
            $this->recordFailure($user, $ip, $userAgent);
            throw new InvalidCredentialsException();
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            throw new AccountSuspendedException();
        }
        if (!$user->isActive() && !$user->isPendingVerification()) {
            // invited (no password yet in practice) or anything unexpected
            throw new InvalidCredentialsException();
        }

        $newDevice = $this->events->hasSucceededBefore($user->id) && !$this->events->isKnownDevice($user->id, $ip, $userAgent);

        $this->events->record($user->id, $ip, $userAgent, true);
        $changes = [
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => $now,
            'last_login_ip' => ClientIp::toBinary($ip),
        ];
        if ($user->passwordHash !== null && $this->hasher->needsRehash($user->passwordHash)) {
            $changes['password_hash'] = $this->hasher->hash($password);
        }
        $this->users->update($user->id, $changes);
        $this->audit->log($user, AuditEvent::AUTH_LOGIN_SUCCESS, 'user', $user->uuid, null, [], $ip);

        if ($newDevice) {
            $this->mailer->send('new_sign_in', $user->email, $user->locale, [
                'first_name' => $user->firstName,
                'at' => $now->format(DATE_ATOM),
                'ip' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 200),
            ]);
        }

        $fresh = $this->users->findById($user->id) ?? $user;

        return $this->auth->startSession($fresh);
    }

    private function recordFailure(?User $user, ?string $ip, string $userAgent): void
    {
        if ($user === null) {
            // Unknown email: nothing to lock. Audited without the address, which may be personal data.
            $this->audit->log(null, AuditEvent::AUTH_LOGIN_FAILED, null, null, null, ['reason' => 'unknown_email'], $ip);

            return;
        }

        $this->events->record($user->id, $ip, $userAgent, false);
        $failures = $this->events->recentFailures($user->id, self::FAILURE_WINDOW);
        $this->audit->log(null, AuditEvent::AUTH_LOGIN_FAILED, 'user', $user->uuid, null, ['failures' => $failures], $ip);

        $changes = ['failed_login_count' => min($failures, 65535)];
        if ($failures >= self::MAX_FAILURES) {
            $changes['locked_until'] = $this->clock->now()->modify('+' . self::LOCK_SECONDS . ' seconds');
            $this->audit->log(null, AuditEvent::AUTH_LOCKOUT, 'user', $user->uuid, null, ['failures' => $failures], $ip);
        }
        $this->users->update($user->id, $changes);
    }
}
