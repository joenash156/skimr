<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Google\GoogleClient;
use App\Auth\Google\GoogleIdentity;
use App\Auth\Password\PasswordHasher;
use App\Security\Audit\AuditEvent;
use App\Shared\Clock\Clock;
use App\Shared\Clock\FrozenClock;
use App\Shared\Mail\Mailer;
use App\Tests\DatabaseTestCase;
use App\Tests\Support\FakeGoogleClient;
use App\Tests\Support\SpyMailer;
use App\Users\UserRepository;
use Psr\Http\Message\ResponseInterface;

/** Google sign-in (B1 task 12), end to end with a fake Google. */
final class GoogleFlowTest extends DatabaseTestCase
{
    private const PASSWORD = 'river-Lantern-42';
    private const ORIGIN = 'http://localhost';

    private FakeGoogleClient $google;

    protected function setUp(): void
    {
        $this->google = new FakeGoogleClient();
        parent::setUp();
    }

    protected function containerOverrides(): array
    {
        return [
            GoogleClient::class => $this->google,
            Mailer::class => new SpyMailer(),
            Clock::class => new FrozenClock('2026-11-20 10:00:00'),
        ];
    }

    public function testRedirectStoresStateInASignedLaxCookieAndGoesToGoogle(): void
    {
        $response = $this->request('GET', '/api/v1/auth/google/redirect');

        self::assertSame(302, $response->getStatusCode());
        self::assertStringStartsWith('https://accounts.google.com/', $response->getHeaderLine('Location'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringStartsWith('__Secure-gstate=', $cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Lax', $cookie);
        self::assertStringContainsString('Path=/api/v1/auth/google', $cookie);
    }

    public function testANewGoogleUserGetsAnAccountAndIsSentToCompleteProfile(): void
    {
        $this->google->willReturn('code-new', new GoogleIdentity('g-111', 'New.Person@Gmail.com', true, 'Abena', 'Asante'));

        $response = $this->googleCallback('code-new');

        self::assertSame('http://localhost/app/complete-profile', $response->getHeaderLine('Location'));
        self::assertTrue($this->setsCookie($response, '__Secure-rt='), 'Refresh cookie must be set.');
        $user = $this->service(UserRepository::class)->findByGoogleSub('g-111');
        self::assertNotNull($user);
        self::assertSame('new.person@gmail.com', $user->email);
        self::assertSame('Abena', $user->firstName);
        self::assertSame('active', $user->status);
        self::assertNull($user->passwordHash);
        self::assertNotNull($user->emailVerifiedAt);
    }

    public function testAReturningLinkedUserIsSignedIn(): void
    {
        $user = $this->createUser(['google_sub' => 'g-222']);
        $this->google->willReturn('code-back', new GoogleIdentity('g-222', $user->email, true, null, null));

        $response = $this->googleCallback('code-back');

        self::assertSame('http://localhost/app', $response->getHeaderLine('Location'));
        self::assertTrue($this->setsCookie($response, '__Secure-rt='));
        self::assertCount(1, $this->auditRows(AuditEvent::AUTH_LOGIN_SUCCESS));
    }

    public function testGoogleLinkingToAPasswordAccountRequiresThePassword(): void
    {
        $owner = $this->createUser(['password_hash' => $this->service(PasswordHasher::class)->hash(self::PASSWORD)]);
        $this->google->willReturn('code-link', new GoogleIdentity('g-333', $owner->email, true, null, null));

        // Callback: no sign-in yet, the user is asked for their password.
        $callback = $this->googleCallback('code-link');
        self::assertSame('http://localhost/app/login?link=google', $callback->getHeaderLine('Location'));
        self::assertFalse($this->setsCookie($callback, '__Secure-rt='), 'No session before the password is confirmed.');
        $linkCookie = $this->cookieValue($callback, '__Secure-glink');

        // Wrong password: refused, nothing linked.
        $wrong = $this->link($linkCookie, 'not-the-password-1');
        self::assertSame(401, $wrong->getStatusCode());
        self::assertNull($this->service(UserRepository::class)->findByGoogleSub('g-333'));

        // Right password: linked and logged in.
        $right = $this->link($linkCookie, self::PASSWORD);
        self::assertSame(200, $right->getStatusCode(), (string) $right->getBody());
        self::assertTrue(self::json($right)['data']['user']['google_linked']);
        self::assertSame($owner->id, $this->service(UserRepository::class)->findByGoogleSub('g-333')?->id);
        self::assertCount(1, $this->auditRows(AuditEvent::AUTH_GOOGLE_LINKED));

        // Next time Google signs them straight in.
        $this->google->willReturn('code-again', new GoogleIdentity('g-333', $owner->email, true, null, null));
        self::assertSame('http://localhost/app', $this->googleCallback('code-again')->getHeaderLine('Location'));
    }

    public function testLinkNeedsAValidCookieAndCsrfHeaders(): void
    {
        $tampered = $this->link('eyJzdWIiOiJnLTEifQ.forged', self::PASSWORD);
        self::assertSame(400, $tampered->getStatusCode());
        self::assertSame('INVALID_TOKEN', self::json($tampered)['error']['code']);

        $noCsrf = $this->app()->handle(
            $this->buildRequest('POST', '/api/v1/auth/google/link', [], ['password' => self::PASSWORD]),
        );
        self::assertSame(403, $noCsrf->getStatusCode());
    }

    public function testUnverifiedGoogleEmailsBadStateAndCancellationAllGoToTheLoginError(): void
    {
        $this->google->willReturn('code-unverified', new GoogleIdentity('g-444', 'x@example.com', false, null, null));
        self::assertSame('http://localhost/app/login?error=google', $this->googleCallback('code-unverified')->getHeaderLine('Location'));
        self::assertNull($this->service(UserRepository::class)->findByEmail('x@example.com'));

        $this->google->willReturn('code-ok', new GoogleIdentity('g-555', 'y@example.com', true, null, null));
        self::assertSame('http://localhost/app/login?error=google', $this->googleCallback('code-ok', state: 'forged-state')->getHeaderLine('Location'));
        self::assertSame('http://localhost/app/login?error=google', $this->googleCallback('code-ok', withCookie: false)->getHeaderLine('Location'));

        $cancelled = $this->app()->handle(
            $this->buildRequest('GET', '/api/v1/auth/google/callback?error=access_denied')
                ->withQueryParams(['error' => 'access_denied']),
        );
        self::assertSame('http://localhost/app/login?error=google', $cancelled->getHeaderLine('Location'));
    }

    public function testAnEmailLinkedToAnotherGoogleAccountIsRefused(): void
    {
        $user = $this->createUser(['google_sub' => 'g-original', 'password_hash' => $this->service(PasswordHasher::class)->hash(self::PASSWORD)]);
        $this->google->willReturn('code-other', new GoogleIdentity('g-intruder', $user->email, true, null, null));

        self::assertSame('http://localhost/app/login?error=google', $this->googleCallback('code-other')->getHeaderLine('Location'));
    }

    // ---------------------------------------------------------------- helpers

    private function googleCallback(string $code, string $state = FakeGoogleClient::STATE, bool $withCookie = true): ResponseInterface
    {
        $redirect = $this->request('GET', '/api/v1/auth/google/redirect');
        $stateCookie = $this->cookieValue($redirect, '__Secure-gstate');

        $request = $this->buildRequest('GET', '/api/v1/auth/google/callback')
            ->withQueryParams(['code' => $code, 'state' => $state]);
        if ($withCookie) {
            $request = $request->withCookieParams(['__Secure-gstate' => $stateCookie]);
        }

        $response = $this->app()->handle($request);
        self::assertSame(302, $response->getStatusCode());

        return $response;
    }

    private function link(string $linkCookie, string $password): ResponseInterface
    {
        $request = $this->buildRequest('POST', '/api/v1/auth/google/link', ['Origin' => self::ORIGIN, 'X-Requested-With' => 'skimr'], ['password' => $password])
            ->withCookieParams(['__Secure-glink' => $linkCookie]);

        return $this->app()->handle($request);
    }

    private function cookieValue(ResponseInterface $response, string $name): string
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (preg_match('/^' . preg_quote($name, '/') . '=([^;]+);/', $header, $m)) {
                return $m[1];
            }
        }
        self::fail('No ' . $name . ' cookie set.');
    }

    private function setsCookie(ResponseInterface $response, string $prefix): bool
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (str_starts_with($header, $prefix) && !str_contains($header, 'Max-Age=0')) {
                return true;
            }
        }

        return false;
    }
}
