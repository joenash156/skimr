<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Google\GoogleAuthException;
use App\Auth\Google\LeagueGoogleClient;
use App\Auth\Google\SignedCookie;
use App\Shared\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/** The id_token claim checks (spec 11.2) and the signed OAuth cookies. */
final class GoogleIdTokenTest extends TestCase
{
    private const CLIENT_ID = 'skimr-client.apps.googleusercontent.com';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock('2026-11-20 10:00:00');
    }

    private function client(): LeagueGoogleClient
    {
        return new LeagueGoogleClient(self::CLIENT_ID, 'secret', 'http://localhost/api/v1/auth/google/callback', $this->clock);
    }

    /** @param array<string, mixed> $claims */
    private function idToken(array $claims): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $b64('{"alg":"RS256"}') . '.' . $b64((string) json_encode($claims + [
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'exp' => $this->clock->now()->getTimestamp() + 3600,
            'sub' => '1234567890',
            'email' => 'Ama@Gmail.com',
            'email_verified' => true,
            'given_name' => 'Ama',
            'family_name' => 'Mensah',
        ])) . '.sig';
    }

    public function testAValidTokenGivesTheIdentity(): void
    {
        $identity = $this->client()->identityFrom($this->idToken([]));

        self::assertSame('1234567890', $identity->sub);
        self::assertSame('ama@gmail.com', $identity->email);
        self::assertTrue($identity->emailVerified);
        self::assertSame('Ama', $identity->givenName);
    }

    public function testRejectsWrongAudienceIssuerExpiredOrMissingClaims(): void
    {
        foreach ([
            ['aud' => 'someone-elses-app'],
            ['iss' => 'https://evil.example'],
            ['exp' => $this->clock->now()->getTimestamp() - 1],
            ['sub' => ''],
            ['email' => 'not-an-email'],
        ] as $bad) {
            try {
                $this->client()->identityFrom($this->idToken($bad));
                self::fail('Expected rejection for ' . json_encode($bad));
            } catch (GoogleAuthException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testUnverifiedEmailIsReportedNotHidden(): void
    {
        self::assertFalse($this->client()->identityFrom($this->idToken(['email_verified' => false]))->emailVerified);
        self::assertTrue($this->client()->identityFrom($this->idToken(['email_verified' => 'true']))->emailVerified);
    }

    public function testNotConfiguredWithoutCredentials(): void
    {
        self::assertFalse((new LeagueGoogleClient(null, null, null, $this->clock))->isConfigured());
        self::assertTrue($this->client()->isConfigured());
        self::assertStringContainsString('code_challenge_method=S256', $this->client()->start()['url']);
    }

    public function testSignedCookieRejectsTamperingAndExpiry(): void
    {
        $cookie = new SignedCookie('__Secure-gstate', str_repeat('k', 32), $this->clock);
        $header = $cookie->issue(['state' => 'abc'], 600);
        preg_match('/^__Secure-gstate=([^;]+);/', $header, $m);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');

        self::assertSame('abc', $cookie->read($request->withCookieParams(['__Secure-gstate' => $m[1]]))['state'] ?? null);

        [$body, $sig] = explode('.', $m[1]);
        $forgedBody = rtrim(strtr(base64_encode('{"state":"evil","exp":9999999999}'), '+/', '-_'), '=');
        self::assertNull($cookie->read($request->withCookieParams(['__Secure-gstate' => $forgedBody . '.' . $sig])));
        self::assertNull((new SignedCookie('__Secure-glink', str_repeat('k', 32), $this->clock))
            ->read($request->withCookieParams(['__Secure-glink' => $m[1]])), 'A cookie signed for one name must not work under another.');

        $this->clock->advance('+11 minutes');
        self::assertNull($cookie->read($request->withCookieParams(['__Secure-gstate' => $m[1]])));
        self::assertNotSame('', $body);
    }
}
