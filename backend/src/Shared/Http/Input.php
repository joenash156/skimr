<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Exception\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads and validates a request body field by field (chain step 8, spec 11).
 * Every reader records a field error instead of throwing, so the client gets
 * all problems at once; call validate() at the end to throw one 422.
 *
 *     $in = Input::fromRequest($request);
 *     $email = $in->email('email');
 *     $name = $in->requiredString('first_name', 80);
 *     $in->validate(); // 422 VALIDATION_FAILED with fields => translation keys
 *
 * Error values are translation keys from translations/api/shared.*.json.
 */
final class Input
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $body */
    public function __construct(private readonly array $body)
    {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $clean = [];
        if (is_array($body)) {
            foreach ($body as $key => $value) {
                $clean[(string) $key] = $value;
            }
        }

        return new self($clean);
    }

    /** Trimmed, required, $min..$max characters. Returns '' when invalid. */
    public function requiredString(string $field, int $max, int $min = 1): string
    {
        $value = $this->body[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return $this->fail($field, 'validation.required', '');
        }
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            return $this->fail($field, 'validation.too_long', '');
        }
        if (mb_strlen($value) < $min) {
            return $this->fail($field, 'validation.too_short', '');
        }

        return $value;
    }

    /** Trimmed; null when absent, null or empty. */
    public function optionalString(string $field, int $max): ?string
    {
        $value = $this->body[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (!is_string($value)) {
            return $this->fail($field, 'validation.invalid', null);
        }
        $value = trim($value);

        return mb_strlen($value) > $max ? $this->fail($field, 'validation.too_long', null) : $value;
    }

    /** Required, untrimmed (passwords keep their spaces), at most $maxBytes. */
    public function secret(string $field, int $maxBytes = 1024): string
    {
        $value = $this->body[$field] ?? null;
        if (!is_string($value) || $value === '') {
            return $this->fail($field, 'validation.required', '');
        }

        return strlen($value) > $maxBytes ? $this->fail($field, 'validation.too_long', '') : $value;
    }

    /** Required, valid, at most 254 characters, returned lowercased. */
    public function email(string $field = 'email'): string
    {
        $value = $this->requiredString($field, 254);
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fail($field, 'validation.email', '');
        }

        return mb_strtolower($value);
    }

    /** Optional E.164 phone number, e.g. +233241234567. */
    public function optionalPhone(string $field): ?string
    {
        $value = $this->optionalString($field, 20);
        if ($value === null) {
            return null;
        }

        return preg_match('/^\+[1-9][0-9]{6,14}$/', $value) ? $value : $this->fail($field, 'validation.phone', null);
    }

    /** Optional 'en' | 'fr' | 'es'. */
    public function optionalLocale(string $field = 'locale'): ?string
    {
        $value = $this->optionalString($field, 5);
        if ($value === null) {
            return null;
        }

        return in_array($value, ['en', 'fr', 'es'], true) ? $value : $this->fail($field, 'validation.invalid', null);
    }

    /** True when the body contains the field at all (even as null). For partial updates (PATCH). */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->body);
    }

    /** @return list<string> the field names present in the body */
    public function keys(): array
    {
        return array_keys($this->body);
    }

    /**
     * Required, and one of $allowed.
     *
     * @param list<string> $allowed
     */
    public function oneOf(string $field, array $allowed): string
    {
        $value = $this->body[$field] ?? null;

        return is_string($value) && in_array($value, $allowed, true) ? $value : $this->fail($field, 'validation.invalid', '');
    }

    /**
     * An object of booleans limited to $allowedKeys, e.g. notification_prefs.
     *
     * @param list<string> $allowedKeys
     * @return array<string, bool>
     */
    public function booleanMap(string $field, array $allowedKeys): array
    {
        $value = $this->body[$field] ?? null;
        if (!is_array($value)) {
            return $this->fail($field, 'validation.invalid', []);
        }

        $map = [];
        foreach ($value as $key => $flag) {
            if (!in_array($key, $allowedKeys, true) || !is_bool($flag)) {
                return $this->fail($field, 'validation.invalid', []);
            }
            $map[(string) $key] = $flag;
        }

        return $map;
    }

    /** The field must be boolean true (e.g. accept_terms). */
    public function accepted(string $field): bool
    {
        return ($this->body[$field] ?? null) === true ? true : $this->fail($field, 'validation.accepted', false);
    }

    /** The field must equal $expected exactly (e.g. password_confirmation). */
    public function confirms(string $field, string $expected): void
    {
        $value = $this->body[$field] ?? null;
        if (!is_string($value) || !hash_equals($expected, $value)) {
            $this->fail($field, 'validation.confirmation', null);
        }
    }

    /** Adds an error for a field (e.g. from a domain rule like PasswordPolicy). The first error per field wins. */
    public function addError(string $field, string $translationKey): void
    {
        $this->errors[$field] ??= $translationKey;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /** @throws ValidationException when any field failed */
    public function validate(): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }

    /**
     * @template T
     * @param T $fallback
     * @return T
     */
    private function fail(string $field, string $key, mixed $fallback): mixed
    {
        $this->addError($field, $key);

        return $fallback;
    }
}
