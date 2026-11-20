<?php

declare(strict_types=1);

namespace App\Auth\Password;

/**
 * bcrypt password hashing (spec 11.2: cost 12).
 */
final class PasswordHasher
{
    /**
     * A valid bcrypt hash of a random string, checked when the account has no
     * password hash (unknown email, Google-only account). It makes "no such
     * user" take as long as "wrong password", so timing can't reveal accounts.
     */
    private const DUMMY_HASH = '$2y$12$cD/ZoJXrhgbBi10GQYPAnu8ARu2C57uosn6.ynrLU4kjccEm5lxay';

    public function __construct(private readonly int $cost = 12)
    {
        if ($cost < 4 || $cost > 31) {
            throw new \InvalidArgumentException('bcrypt cost must be between 4 and 31.');
        }
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    /** Always does one bcrypt check, even when $hash is null. */
    public function verify(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            password_verify($password, self::DUMMY_HASH);

            return false;
        }

        return password_verify($password, $hash);
    }

    /** True when the hash used a different cost; re-hash it after a successful login. */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }
}
