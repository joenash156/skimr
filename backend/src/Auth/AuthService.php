<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exception\SessionExpiredException;
use App\Auth\Jwt\JwtService;
use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Shared\Clock\Clock;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Mail\Mailer;
use App\Users\User;
use App\Users\UserRepository;

/**
 * Sessions (spec 11.1): one refresh token per user, rotated on every refresh,
 * with reuse detection.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly JwtService $jwt,
        private readonly AuditLogger $audit,
        private readonly Mailer $mailer,
        private readonly Clock $clock,
    ) {
    }

    /** Issues both tokens and stores the refresh hash, replacing any older session. */
    public function startSession(User $user): Session
    {
        $refresh = $this->jwt->issueRefreshToken($user);
        $this->users->startSession($user->id, $refresh->hash(), $refresh->expiresAt);

        return new Session($user, $this->jwt->issueAccessToken($user), $refresh);
    }

    /**
     * Swaps a refresh token for a new pair.
     *
     * - Matches the current hash: rotate (old hash moves to refresh_token_prev_hash).
     * - Matches the previous hash: a stolen token was replayed. Sign out
     *   everywhere, audit it, email the user, and answer 401 REFRESH_REUSED.
     * - Anything else: 401.
     *
     * @throws SessionExpiredException
     */
    public function refresh(string $refreshToken, ?string $ip = null): Session
    {
        try {
            $claims = $this->jwt->verifyRefreshToken($refreshToken);
        } catch (UnauthorizedException) {
            throw SessionExpiredException::unauthenticated();
        }

        $user = $this->users->findByUuid($claims->userUuid);
        if ($user === null) {
            throw SessionExpiredException::unauthenticated();
        }

        $presented = hash('sha256', $refreshToken);

        if ($user->refreshTokenPrevHash !== null && hash_equals($user->refreshTokenPrevHash, $presented)) {
            $this->handleReuse($user, $ip);
        }

        if (
            $user->refreshTokenHash === null
            || !hash_equals($user->refreshTokenHash, $presented)
            || ($user->refreshTokenExpiresAt !== null && $user->refreshTokenExpiresAt <= $this->clock->now())
        ) {
            throw SessionExpiredException::unauthenticated();
        }

        if (!$user->isActive() && !$user->isPendingVerification()) {
            // Suspended (or otherwise blocked) since the token was issued.
            $this->users->endSession($user->id);
            throw SessionExpiredException::unauthenticated();
        }

        $next = $this->jwt->issueRefreshToken($user);
        if (!$this->users->rotateRefreshToken($user->id, $presented, $next->hash(), $next->expiresAt)) {
            // Another request rotated this exact token a moment ago: it is now the
            // previous token, so presenting it again counts as reuse.
            $this->handleReuse($user, $ip);
        }

        return new Session($user, $this->jwt->issueAccessToken($user), $next);
    }

    /** Logout (spec 5.2): forget the refresh token. Unknown or invalid tokens are ignored. */
    public function logout(?string $refreshToken): void
    {
        if ($refreshToken === null) {
            return;
        }
        try {
            $claims = $this->jwt->verifyRefreshToken($refreshToken);
        } catch (UnauthorizedException) {
            return;
        }

        $user = $this->users->findByUuid($claims->userUuid);
        if ($user !== null && $user->refreshTokenHash !== null && hash_equals($user->refreshTokenHash, hash('sha256', $refreshToken))) {
            $this->users->endSession($user->id);
        }
    }

    /**
     * Sign out everywhere (spec 11.1): clears the refresh token and bumps
     * token_version, which cancels every access token on its next use.
     * Used after a password reset or change, and by admins (P2).
     */
    public function signOutEverywhere(User $user): void
    {
        $this->users->revokeAllSessions($user->id);
    }

    private function handleReuse(User $user, ?string $ip): never
    {
        $this->signOutEverywhere($user);
        $this->audit->log($user, AuditEvent::AUTH_REFRESH_REUSE_DETECTED, 'user', $user->uuid, null, [], $ip);
        $this->mailer->send('suspicious_activity', $user->email, $user->locale, [
            'first_name' => $user->firstName,
            'at' => $this->clock->now()->format(DATE_ATOM),
        ]);

        throw SessionExpiredException::reused();
    }
}
