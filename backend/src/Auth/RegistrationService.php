<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Password\PasswordHasher;
use App\Shared\Exception\ConflictException;
use App\Users\User;
use App\Users\UserRepository;

/**
 * Sign-up (spec 5.2, P1 brief B1 task 7). Input is validated by the
 * controller; this creates the pending account and sends the verify email.
 */
final class RegistrationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly EmailVerificationService $verification,
    ) {
    }

    /**
     * @param array{first_name: string, last_name: string, other_names: ?string, email: string, phone: ?string, locale: string} $profile
     *
     * @throws ConflictException when the email is already registered
     */
    public function register(array $profile, string $password): User
    {
        if ($this->users->emailExists($profile['email'])) {
            throw new ConflictException('auth.errors.email_taken');
        }

        try {
            $user = $this->users->create([
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'other_names' => $profile['other_names'],
                'email' => $profile['email'],
                'phone' => $profile['phone'],
                'locale' => $profile['locale'],
                'role' => User::ROLE_USER,
                'status' => User::STATUS_PENDING_VERIFICATION,
                'password_hash' => $this->hasher->hash($password),
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Two sign-ups with the same email at the same moment.
            throw new ConflictException('auth.errors.email_taken', [], $e);
        }

        $this->verification->send($user);

        return $user;
    }
}
