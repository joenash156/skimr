<?php

declare(strict_types=1);

namespace App\Auth\Jwt;

use App\Shared\Clock\Clock;
use App\Shared\Exception\UnauthorizedException;
use App\Users\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Signs and verifies the two JWTs (spec 11.1).
 *
 * - Access token: HS256 with JWT_ACCESS_SECRET, 15 minutes.
 *   Claims sub (user uuid), role, tv (token_version), iat, exp, iss, aud="api", jti.
 * - Refresh token: HS256 with JWT_REFRESH_SECRET, 14 days.
 *   Claims sub, jti, iat, exp.
 *
 * The secrets are different, so one kind of token can never pass as the other.
 * Every verification failure becomes a plain 401, with no detail on why.
 */
final class JwtService
{
    private const ALG = 'HS256';
    private const AUDIENCE = 'api';
    private const MIN_SECRET_BYTES = 32;

    private readonly string $accessSecret;
    private readonly string $refreshSecret;

    public function __construct(
        ?string $accessSecret,
        ?string $refreshSecret,
        private readonly string $issuer,
        private readonly Clock $clock,
        private readonly int $accessTtl = 900,
        private readonly int $refreshTtl = 1209600,
    ) {
        $this->accessSecret = self::checkedSecret('JWT_ACCESS_SECRET', $accessSecret);
        $this->refreshSecret = self::checkedSecret('JWT_REFRESH_SECRET', $refreshSecret);
        if (hash_equals($this->accessSecret, $this->refreshSecret)) {
            throw new \RuntimeException('JWT_ACCESS_SECRET and JWT_REFRESH_SECRET must be different.');
        }
    }

    public function issueAccessToken(User $user): IssuedToken
    {
        $now = $this->clock->now()->getTimestamp();
        $jti = bin2hex(random_bytes(16));
        $exp = $now + $this->accessTtl;

        $token = JWT::encode([
            'iss' => $this->issuer,
            'aud' => self::AUDIENCE,
            'sub' => $user->uuid,
            'role' => $user->role,
            'tv' => $user->tokenVersion,
            'iat' => $now,
            'exp' => $exp,
            'jti' => $jti,
        ], $this->accessSecret, self::ALG);

        return new IssuedToken($token, $jti, self::at($exp));
    }

    public function issueRefreshToken(User $user): IssuedToken
    {
        $now = $this->clock->now()->getTimestamp();
        $jti = bin2hex(random_bytes(16));
        $exp = $now + $this->refreshTtl;

        $token = JWT::encode([
            'sub' => $user->uuid,
            'jti' => $jti,
            'iat' => $now,
            'exp' => $exp,
        ], $this->refreshSecret, self::ALG);

        return new IssuedToken($token, $jti, self::at($exp));
    }

    /** @throws UnauthorizedException */
    public function verifyAccessToken(string $token): AccessClaims
    {
        $claims = $this->decode($token, $this->accessSecret);

        if (($claims['iss'] ?? null) !== $this->issuer || ($claims['aud'] ?? null) !== self::AUDIENCE) {
            throw new UnauthorizedException();
        }
        if (
            !is_string($claims['sub'] ?? null)
            || !in_array($claims['role'] ?? null, User::ROLES, true)
            || !is_int($claims['tv'] ?? null)
            || !is_string($claims['jti'] ?? null)
            || !is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
        ) {
            throw new UnauthorizedException();
        }

        return new AccessClaims($claims['sub'], $claims['role'], $claims['tv'], $claims['jti'], $claims['iat'], $claims['exp']);
    }

    /** @throws UnauthorizedException */
    public function verifyRefreshToken(string $token): RefreshClaims
    {
        $claims = $this->decode($token, $this->refreshSecret);

        if (
            !is_string($claims['sub'] ?? null)
            || !is_string($claims['jti'] ?? null)
            || !is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
        ) {
            throw new UnauthorizedException();
        }

        return new RefreshClaims($claims['sub'], $claims['jti'], $claims['iat'], $claims['exp']);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UnauthorizedException
     */
    private function decode(string $token, string $secret): array
    {
        if ($token === '' || strlen($token) > 4096 || substr_count($token, '.') !== 2) {
            throw new UnauthorizedException();
        }

        // php-jwt reads the current time from a static property; point it at our clock
        // for this call only, so tests with a FrozenClock see consistent expiry.
        $previous = JWT::$timestamp;
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $decoded = JWT::decode($token, new Key($secret, self::ALG));
        } catch (\UnexpectedValueException | \DomainException | \InvalidArgumentException $e) {
            // Covers bad signature, expired, not-yet-valid, wrong algorithm and malformed tokens.
            throw new UnauthorizedException(previous: $e);
        } finally {
            JWT::$timestamp = $previous;
        }

        return (array) $decoded;
    }

    private static function checkedSecret(string $name, ?string $secret): string
    {
        if ($secret === null || strlen($secret) < self::MIN_SECRET_BYTES || str_contains($secret, 'change-me')) {
            throw new \RuntimeException(sprintf(
                '%s is missing, a placeholder, or shorter than %d bytes. Generate one with: php -r "echo bin2hex(random_bytes(64)), PHP_EOL;"',
                $name,
                self::MIN_SECRET_BYTES,
            ));
        }

        return $secret;
    }

    private static function at(int $timestamp): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $timestamp);
    }
}
