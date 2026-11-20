<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Token\TokenType;
use App\Auth\Token\UserTokenService;
use App\Shared\Clock\Clock;
use App\Shared\Exception\InvalidTokenException;
use App\Shared\Mail\Mailer;
use App\Users\User;
use App\Users\UserRepository;

/**
 * Email verification (spec 5.2): a 24-hour link; opening it activates a
 * pending account.
 */
final class EmailVerificationService
{
    public const TTL = 86400;

    public function __construct(
        private readonly UserTokenService $tokens,
        private readonly UserRepository $users,
        private readonly AuthLinks $links,
        private readonly Mailer $mailer,
        private readonly Clock $clock,
    ) {
    }

    /** Issues a fresh link (cancelling earlier ones) and emails it. */
    public function send(User $user): void
    {
        $token = $this->tokens->issue($user->id, TokenType::VERIFY_EMAIL, self::TTL);
        $this->mailer->send('verify_email', $user->email, $user->locale, [
            'first_name' => $user->firstName,
            'link' => $this->links->verifyEmail($token),
            'expires_hours' => intdiv(self::TTL, 3600),
        ]);
    }

    /** @throws InvalidTokenException */
    public function verify(string $token): User
    {
        $consumed = $this->tokens->consume($token, TokenType::VERIFY_EMAIL);
        $user = $this->users->findById($consumed->userId) ?? throw new InvalidTokenException();

        $changes = [];
        if ($user->emailVerifiedAt === null) {
            $changes['email_verified_at'] = $this->clock->now();
        }
        if ($user->isPendingVerification()) {
            $changes['status'] = User::STATUS_ACTIVE;
        }
        if ($changes !== []) {
            $this->users->update($user->id, $changes);
        }

        return $this->users->findById($user->id) ?? $user;
    }
}
