<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Password\PasswordHasher;
use App\Auth\Token\TokenType;
use App\Auth\Token\UserTokenService;
use App\Shared\Clock\Clock;
use App\Shared\Exception\InvalidTokenException;
use App\Users\User;
use App\Users\UserRepository;

/**
 * An invited admin (or the first super admin from the CLI) opens the emailed
 * link and sets their first password (P1 brief B1 task 11).
 */
final class InviteService
{
    public const TTL = 86400;

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserTokenService $tokens,
        private readonly PasswordHasher $hasher,
        private readonly AuthService $auth,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Sets the password, activates the account, marks the email verified and
     * starts a session.
     *
     * @throws InvalidTokenException
     */
    public function accept(string $token, string $password): Session
    {
        $consumed = $this->tokens->consume($token, TokenType::ADMIN_INVITE);
        $user = $this->users->findById($consumed->userId) ?? throw new InvalidTokenException();
        if ($user->status !== User::STATUS_INVITED) {
            throw new InvalidTokenException();
        }

        $this->users->update($user->id, [
            'password_hash' => $this->hasher->hash($password),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $this->clock->now(),
        ]);

        $active = $this->users->findById($user->id) ?? throw new InvalidTokenException();

        return $this->auth->startSession($active);
    }
}
