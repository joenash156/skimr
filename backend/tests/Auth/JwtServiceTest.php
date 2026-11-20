<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Jwt\JwtService;
use App\Shared\Clock\FrozenClock;
use App\Shared\Exception\UnauthorizedException;
use App\Users\User;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private const ACCESS = 'access-secret-for-tests-0123456789abcdef';
    private const REFRESH = 'refresh-secret-for-tests-0123456789abcdef';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock('2026-11-20 10:00:00');
    }

    private function jwt(string $issuer = 'skimr.test'): JwtService
    {
        return new JwtService(self::ACCESS, self::REFRESH, $issuer, $this->clock);
    }

    private static function user(string $role = User::ROLE_USER, int $tokenVersion = 3): User
    {
        return User::fromRow([
            'id' => 7, 'uuid' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'role' => $role, 'status' => 'active',
            'first_name' => 'Ama', 'last_name' => 'Mensah', 'email' => 'ama@example.com', 'locale' => 'en',
            'theme' => 'system', 'citation_style' => 'apa7', 'token_version' => $tokenVersion, 'failed_login_count' => 0,
            'created_at' => '2026-01-01 00:00:00.000', 'updated_at' => '2026-01-01 00:00:00.000',
        ]);
    }

    public function testAccessTokenRoundTrip(): void
    {
        $issued = $this->jwt()->issueAccessToken(self::user(User::ROLE_ADMIN));
        $claims = $this->jwt()->verifyAccessToken($issued->token);

        self::assertSame('0f8fad5b-d9cb-469f-a165-70867728950e', $claims->userUuid);
        self::assertSame('admin', $claims->role);
        self::assertSame(3, $claims->tokenVersion);
        self::assertSame($issued->jti, $claims->jti);
        self::assertSame(900, $claims->expiresAt - $claims->issuedAt);
        self::assertSame(64, strlen($issued->hash()));
    }

    public function testRefreshTokenRoundTripLasts14Days(): void
    {
        $issued = $this->jwt()->issueRefreshToken(self::user());
        $claims = $this->jwt()->verifyRefreshToken($issued->token);

        self::assertSame('0f8fad5b-d9cb-469f-a165-70867728950e', $claims->userUuid);
        self::assertSame(1209600, $claims->expiresAt - $claims->issuedAt);
    }

    public function testAccessTokenExpiresAfter15Minutes(): void
    {
        $token = $this->jwt()->issueAccessToken(self::user())->token;
        $this->clock->advance('+14 minutes');
        $this->jwt()->verifyAccessToken($token);

        $this->clock->advance('+2 minutes');
        $this->expectException(UnauthorizedException::class);
        $this->jwt()->verifyAccessToken($token);
    }

    public function testRefreshTokenIsNotAcceptedAsAccessTokenAndViceVersa(): void
    {
        $refresh = $this->jwt()->issueRefreshToken(self::user())->token;
        $access = $this->jwt()->issueAccessToken(self::user())->token;

        $this->assertRejected(fn () => $this->jwt()->verifyAccessToken($refresh));
        $this->assertRejected(fn () => $this->jwt()->verifyRefreshToken($access));
    }

    public function testRejectsTamperedSignatureWrongIssuerAndGarbage(): void
    {
        $token = $this->jwt()->issueAccessToken(self::user())->token;
        [$header, $payload] = explode('.', $token);
        $forged = $header . '.' . $payload . '.' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->assertRejected(fn () => $this->jwt()->verifyAccessToken($forged));
        $this->assertRejected(fn () => $this->jwt('evil.example')->verifyAccessToken($token));
        $this->assertRejected(fn () => $this->jwt()->verifyAccessToken('not-a-jwt'));
        $this->assertRejected(fn () => $this->jwt()->verifyAccessToken(''));
    }

    public function testRejectsAlgNoneAndWrongAudience(): void
    {
        $now = $this->clock->now()->getTimestamp();
        $claims = ['iss' => 'skimr.test', 'aud' => 'api', 'sub' => 'x', 'role' => 'user', 'tv' => 0, 'iat' => $now, 'exp' => $now + 60, 'jti' => 'j'];

        $none = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=') . '.'
            . rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=') . '.';
        $this->assertRejected(fn () => $this->jwt()->verifyAccessToken($none));

        JWT::$timestamp = null;
        $wrongAud = JWT::encode(['aud' => 'other'] + $claims, self::ACCESS, 'HS256');
        $this->assertRejected(fn () => $this->jwt()->verifyAccessToken($wrongAud));
    }

    public function testRefusesPlaceholderShortOrIdenticalSecrets(): void
    {
        foreach ([['change-me-64-random-bytes-xxxxxxxxxxxxxxx', self::REFRESH], ['short', self::REFRESH], [null, self::REFRESH], [self::ACCESS, self::ACCESS]] as [$access, $refresh]) {
            try {
                new JwtService($access, $refresh, 'skimr.test', $this->clock);
                self::fail('Expected the secrets to be refused.');
            } catch (\RuntimeException $e) {
                self::assertNotInstanceOf(UnauthorizedException::class, $e);
            }
        }
    }

    private function assertRejected(callable $verify): void
    {
        try {
            $verify();
            self::fail('Expected the token to be rejected.');
        } catch (UnauthorizedException) {
            $this->addToAssertionCount(1);
        }
    }
}
