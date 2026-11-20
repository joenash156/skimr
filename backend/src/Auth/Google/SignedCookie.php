<?php

declare(strict_types=1);

namespace App\Auth\Google;

use App\Shared\Clock\Clock;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A short-lived, HMAC-signed, HttpOnly cookie holding a small JSON payload.
 * Used during Google sign-in for the OAuth state + PKCE verifier, and for the
 * "link Google to your password account" step. The payload is readable by
 * whoever holds the cookie (it isn't encrypted), so it never holds secrets
 * beyond the one-time OAuth values it exists to carry.
 *
 * SameSite=Lax, not Strict: Google's redirect back to us is a cross-site
 * navigation, and Strict cookies wouldn't be sent with it.
 */
final class SignedCookie
{
    public const PATH = '/api/v1/auth/google';

    public function __construct(
        private readonly string $name,
        private readonly string $key,
        private readonly Clock $clock,
    ) {
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('SignedCookie key must be at least 32 bytes.');
        }
    }

    /** @param array<string, string> $payload */
    public function issue(array $payload, int $ttlSeconds): string
    {
        $body = self::b64(json_encode(
            $payload + ['exp' => $this->clock->now()->getTimestamp() + $ttlSeconds],
            JSON_THROW_ON_ERROR,
        ));

        return $this->header($body . '.' . $this->sign($body), $ttlSeconds);
    }

    /** @return array<string, mixed>|null the payload, or null if missing, tampered with or expired */
    public function read(ServerRequestInterface $request): ?array
    {
        $raw = $request->getCookieParams()[$this->name] ?? null;
        if (!is_string($raw) || strlen($raw) > 4096 || substr_count($raw, '.') !== 1) {
            return null;
        }
        [$body, $signature] = explode('.', $raw);
        if (!hash_equals($this->sign($body), $signature)) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($body, '-_', '+/'), true), true);
        if (!is_array($payload) || !is_int($payload['exp'] ?? null) || $payload['exp'] <= $this->clock->now()->getTimestamp()) {
            return null;
        }

        return $payload;
    }

    public function clearHeader(): string
    {
        return $this->header('', 0);
    }

    private function header(string $value, int $maxAge): string
    {
        return sprintf('%s=%s; Max-Age=%d; Path=%s; Secure; HttpOnly; SameSite=Lax', $this->name, $value, $maxAge, self::PATH);
    }

    private function sign(string $body): string
    {
        return self::b64(hash_hmac('sha256', $this->name . '|' . $body, $this->key, true));
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
