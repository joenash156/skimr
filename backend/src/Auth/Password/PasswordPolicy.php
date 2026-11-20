<?php

declare(strict_types=1);

namespace App\Auth\Password;

use App\Shared\Exception\ValidationException;

/**
 * Password rules (spec 5.2):
 * - 10 to 72 characters (bcrypt ignores anything past 72 bytes)
 * - at least one letter and one number
 * - not one of the 10,000 most common passwords (resources/common-passwords.txt)
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 10;
    public const MAX_BYTES = 72;

    public const ERROR_LENGTH = 'validation.password.length';
    public const ERROR_LETTER_AND_NUMBER = 'validation.password.letter_and_number';
    public const ERROR_COMMON = 'validation.password.common';

    /** @var array<string, true>|null lowercased common passwords, loaded on first use */
    private ?array $common = null;

    public function __construct(private readonly string $commonPasswordsFile)
    {
    }

    /**
     * @return list<string> translation keys of every rule the password breaks; empty when it's fine
     */
    public function violations(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH || strlen($password) > self::MAX_BYTES) {
            $errors[] = self::ERROR_LENGTH;
        }
        if (!preg_match('/\p{L}/u', $password) || !preg_match('/\p{N}/u', $password)) {
            $errors[] = self::ERROR_LETTER_AND_NUMBER;
        }
        if ($this->isCommon($password)) {
            $errors[] = self::ERROR_COMMON;
        }

        return $errors;
    }

    /**
     * Throws 422 with the first broken rule on $field.
     *
     * @throws ValidationException
     */
    public function assertValid(string $password, string $field = 'password'): void
    {
        $errors = $this->violations($password);
        if ($errors !== []) {
            throw new ValidationException([$field => $errors[0]]);
        }
    }

    private function isCommon(string $password): bool
    {
        if ($this->common === null) {
            $lines = @file($this->commonPasswordsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                // Fail loudly: silently skipping the check would weaken every new password.
                throw new \RuntimeException('Common-passwords list not found: ' . $this->commonPasswordsFile);
            }
            $this->common = [];
            foreach ($lines as $line) {
                $this->common[mb_strtolower(trim($line))] = true;
            }
        }

        return isset($this->common[mb_strtolower($password)]);
    }
}
