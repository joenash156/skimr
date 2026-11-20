<?php

declare(strict_types=1);

namespace App\Auth\Google;

use App\Auth\AuthService;
use App\Auth\Exception\InvalidCredentialsException;
use App\Auth\LoginEvents;
use App\Auth\LoginService;
use App\Auth\Session;
use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Shared\Clock\Clock;
use App\Users\User;
use App\Users\UserRepository;

/**
 * Google sign-in decisions (spec 5.2, P1 brief B1 task 12):
 *
 * - Google's email must be verified.
 * - An account already linked to this Google id: sign in.
 * - No account with that email: create one (email verified, no password),
 *   then the app asks the user to complete their profile.
 * - A password account with that email: the user must confirm their password
 *   once to link Google (stops account takeover by someone who controls a
 *   Google account with the same address).
 */
final class GoogleAuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthService $auth,
        private readonly LoginService $login,
        private readonly LoginEvents $events,
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {
    }

    /** @throws GoogleAuthException when this Google account can't be used to sign in */
    public function signIn(GoogleIdentity $google, ?string $ip, string $userAgent, string $locale): GoogleSignInResult
    {
        if (!$google->emailVerified) {
            throw new GoogleAuthException('Google email is not verified.');
        }

        $linked = $this->users->findByGoogleSub($google->sub);
        if ($linked !== null) {
            return GoogleSignInResult::signedIn($this->startSession($linked, $ip, $userAgent));
        }
        if ($this->users->googleSubExists($google->sub)) {
            throw new GoogleAuthException('This Google account belongs to a deleted user.');
        }

        $byEmail = $this->users->findByEmail($google->email);
        if ($byEmail !== null) {
            if ($byEmail->googleSub !== null) {
                throw new GoogleAuthException('The email belongs to an account linked to a different Google account.');
            }
            if ($byEmail->passwordHash === null) {
                // e.g. an invited admin who hasn't set a password yet: nothing to confirm the link with.
                throw new GoogleAuthException('Matching account has no password to confirm a link.');
            }

            return GoogleSignInResult::linkRequired($google->sub, $google->email);
        }
        if ($this->users->emailExists($google->email)) {
            throw new GoogleAuthException('The email belongs to a deleted account.');
        }

        $user = $this->users->create([
            'first_name' => self::name($google->givenName, strstr($google->email, '@', true) ?: 'User', 80),
            'last_name' => self::name($google->familyName, '', 80),
            'email' => $google->email,
            'email_verified_at' => $this->clock->now(),
            'google_sub' => $google->sub,
            'locale' => $locale,
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ]);

        return GoogleSignInResult::newAccount($this->startSession($user, $ip, $userAgent));
    }

    /**
     * Second step of linking: the user typed their password. Goes through the
     * normal password login (same lockout, events and audit), then stores the
     * Google id.
     *
     * @param array<string, mixed> $linkPayload from the signed link cookie: sub, email
     *
     * @throws InvalidCredentialsException|GoogleAuthException
     */
    public function link(array $linkPayload, string $password, ?string $ip, string $userAgent): Session
    {
        $sub = $linkPayload['sub'] ?? null;
        $email = $linkPayload['email'] ?? null;
        if (!is_string($sub) || !is_string($email) || $this->users->googleSubExists($sub)) {
            throw new GoogleAuthException('Link request is no longer valid.');
        }

        $session = $this->login->login($email, $password, $ip, $userAgent);
        if ($session->user->googleSub !== null) {
            throw new GoogleAuthException('Account was linked to Google in the meantime.');
        }

        $this->users->update($session->user->id, ['google_sub' => $sub]);
        $this->audit->log($session->user, AuditEvent::AUTH_GOOGLE_LINKED, 'user', $session->user->uuid, null, [], $ip);

        $linked = $this->users->findById($session->user->id) ?? $session->user;

        return new Session($linked, $session->accessToken, $session->refreshToken);
    }

    private function startSession(User $user, ?string $ip, string $userAgent): Session
    {
        if (!$user->isActive() && !$user->isPendingVerification()) {
            throw new GoogleAuthException('Account status is ' . $user->status . '.');
        }

        $this->events->record($user->id, $ip, $userAgent, true);
        $this->users->update($user->id, [
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => $this->clock->now(),
        ]);
        $this->audit->log($user, AuditEvent::AUTH_LOGIN_SUCCESS, 'user', $user->uuid, null, ['method' => 'google'], $ip);

        return $this->auth->startSession($this->users->findById($user->id) ?? $user);
    }

    private static function name(?string $value, string $fallback, int $max): string
    {
        $value = trim((string) $value);

        return mb_substr($value === '' ? $fallback : $value, 0, $max);
    }
}
