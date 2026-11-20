<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Password\PasswordHasher;
use App\Auth\RefreshCookie;
use App\Security\Audit\AuditEvent;
use App\Security\Turnstile\TurnstileVerifier;
use App\Shared\Clock\Clock;
use App\Shared\Clock\FrozenClock;
use App\Shared\Mail\Mailer;
use App\Tests\DatabaseTestCase;
use App\Tests\Support\FakeTurnstile;
use App\Tests\Support\SpyMailer;
use App\Users\User;
use Psr\Http\Message\ResponseInterface;

/**
 * The B1 acceptance checks, end to end through the real routes and middleware.
 */
final class AuthFlowTest extends DatabaseTestCase
{
    private const ORIGIN = 'http://localhost';
    private const PASSWORD = 'river-Lantern-42';

    private SpyMailer $mail;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->mail = new SpyMailer();
        $this->clock = new FrozenClock('2026-11-20 10:00:00');
        parent::setUp();
    }

    protected function containerOverrides(): array
    {
        return [
            Mailer::class => $this->mail,
            Clock::class => $this->clock,
            TurnstileVerifier::class => FakeTurnstile::verifier(),
        ];
    }

    public function testRegisterVerifyLoginRefreshLogout(): void
    {
        // Register: 201, pending, verify email sent.
        $response = $this->post('/api/v1/auth/register', [
            'first_name' => 'Ama', 'last_name' => 'Mensah', 'email' => 'Ama@Example.com',
            'phone' => '+233241234567', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            'accept_terms' => true, 'locale' => 'fr', 'turnstile_token' => 'ok',
        ]);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $user = self::json($response)['data']['user'];
        self::assertSame('ama@example.com', $user['email']);
        self::assertSame('pending_verification', $user['status']);
        self::assertFalse($user['email_verified']);
        self::assertArrayNotHasKey('password_hash', $user);
        self::assertSame('fr', $this->mail->ofTemplate('verify_email')[0]['locale']);

        // Unverified users can log in; the app shows the verify screen.
        $pending = $this->login('ama@example.com');
        self::assertSame(200, $pending->getStatusCode());
        self::assertSame('pending_verification', self::json($pending)['data']['user']['status']);

        // Verify.
        $verified = $this->post('/api/v1/auth/verify-email', ['token' => $this->mail->lastToken('verify_email')]);
        self::assertSame(200, $verified->getStatusCode());
        self::assertSame('active', self::json($verified)['data']['user']['status']);
        self::assertTrue(self::json($verified)['data']['user']['email_verified']);

        // Login: access token in the body, refresh token only in the cookie.
        $login = $this->login('ama@example.com');
        self::assertSame(200, $login->getStatusCode());
        $data = self::json($login)['data'];
        self::assertSame('Bearer', $data['token_type']);
        self::assertSame(900, $data['expires_in']);
        self::assertSame('no-store', $login->getHeaderLine('Cache-Control'));
        $cookie = $this->cookieFrom($login);
        self::assertStringContainsString('HttpOnly', $login->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('SameSite=Strict', $login->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Path=/api/v1/auth', $login->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('Max-Age=1209600', $login->getHeaderLine('Set-Cookie'));

        // Refresh rotates the cookie.
        $refreshed = $this->refresh($cookie);
        self::assertSame(200, $refreshed->getStatusCode(), (string) $refreshed->getBody());
        $newCookie = $this->cookieFrom($refreshed);
        self::assertNotSame($cookie, $newCookie);

        // Logout forgets the session and expires the cookie.
        $logout = $this->cookieRequest('/api/v1/auth/logout', $newCookie);
        self::assertSame(204, $logout->getStatusCode());
        self::assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));
        self::assertSame(401, $this->refresh($newCookie)->getStatusCode());
    }

    public function testAReplayedOldRefreshTokenClearsTheSessionAndBumpsTokenVersion(): void
    {
        $user = $this->activeUser();
        $first = $this->cookieFrom($this->login($user->email));
        $second = $this->cookieFrom($this->refresh($first));

        // An attacker replays the first (already rotated) token.
        $replay = $this->refresh($first);

        self::assertSame(401, $replay->getStatusCode());
        self::assertSame('REFRESH_REUSED', self::json($replay)['error']['code']);
        self::assertStringContainsString('Max-Age=0', $replay->getHeaderLine('Set-Cookie'));

        $row = $this->db()->fetchAssociative('SELECT token_version, refresh_token_hash, refresh_token_prev_hash FROM users WHERE id = ?', [$user->id]);
        self::assertSame(1, (int) $row['token_version']);
        self::assertNull($row['refresh_token_hash']);
        self::assertNull($row['refresh_token_prev_hash']);
        self::assertCount(1, $this->auditRows(AuditEvent::AUTH_REFRESH_REUSE_DETECTED));
        self::assertCount(1, $this->mail->ofTemplate('suspicious_activity'));

        // The legitimate user's newer token is dead too.
        self::assertSame(401, $this->refresh($second)->getStatusCode());
    }

    public function testFiveFailedLoginsLockTheAccountAndTheSixthIsRefusedEvenWithTheRightPassword(): void
    {
        $user = $this->activeUser();

        // Spread over different IPs, so it is the account lock (not the per email+IP rate limit) that trips.
        for ($i = 1; $i <= 5; $i++) {
            $failed = $this->login($user->email, 'wrong-password-' . $i, '198.51.100.' . $i);
            self::assertSame(401, $failed->getStatusCode());
            self::assertSame('INVALID_CREDENTIALS', self::json($failed)['error']['code']);
        }

        $sixth = $this->login($user->email, self::PASSWORD, '198.51.100.99');
        self::assertSame(429, $sixth->getStatusCode());
        self::assertSame('ACCOUNT_LOCKED', self::json($sixth)['error']['code']);
        self::assertSame('900', $sixth->getHeaderLine('Retry-After'));
        self::assertCount(1, $this->auditRows(AuditEvent::AUTH_LOCKOUT));

        // After 15 minutes the right password works again.
        $this->clock->advance('+15 minutes +1 second');
        self::assertSame(200, $this->login($user->email, self::PASSWORD, '198.51.100.99')->getStatusCode());
    }

    public function testTheSameLoginErrorForUnknownEmailAndWrongPassword(): void
    {
        $user = $this->activeUser();

        $unknown = self::json($this->login('nobody@example.com', self::PASSWORD));
        $wrong = self::json($this->login($user->email, 'not-the-password-1'));

        self::assertSame($unknown['error']['code'], $wrong['error']['code']);
        self::assertSame($unknown['error']['message'], $wrong['error']['message']);
    }

    public function testThePerEmailAndIpRateLimitAnswersRateLimited(): void
    {
        $user = $this->activeUser();
        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, 'wrong-password-1');
        }

        // 6th from the same IP: the rate limit runs first.
        $response = $this->login($user->email, self::PASSWORD);
        self::assertSame(429, $response->getStatusCode());
        self::assertContains(self::json($response)['error']['code'], ['RATE_LIMITED', 'ACCOUNT_LOCKED']);
    }

    public function testAnExpiredOrAlreadyUsedTokenIsRejected(): void
    {
        $this->register('kofi@example.com');
        $token = $this->mail->lastToken('verify_email');

        self::assertSame(200, $this->post('/api/v1/auth/verify-email', ['token' => $token])->getStatusCode());
        $reused = $this->post('/api/v1/auth/verify-email', ['token' => $token]);
        self::assertSame(400, $reused->getStatusCode());
        self::assertSame('INVALID_TOKEN', self::json($reused)['error']['code']);

        $this->post('/api/v1/auth/password/forgot', ['email' => 'kofi@example.com', 'turnstile_token' => 'ok']);
        $reset = $this->mail->lastToken('reset_password');
        $this->clock->advance('+61 minutes');
        $expired = $this->post('/api/v1/auth/password/reset', ['token' => $reset, 'password' => 'brand-New-pass-7', 'password_confirmation' => 'brand-New-pass-7']);
        self::assertSame(400, $expired->getStatusCode());
        self::assertSame('INVALID_TOKEN', self::json($expired)['error']['code']);
    }

    public function testForgotPasswordAnswersTheSameForUnknownEmailsAndResetSignsOutEverywhere(): void
    {
        $user = $this->activeUser();
        $session = $this->cookieFrom($this->login($user->email));

        $unknown = $this->post('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com', 'turnstile_token' => 'ok']);
        $known = $this->post('/api/v1/auth/password/forgot', ['email' => $user->email, 'turnstile_token' => 'ok']);
        self::assertSame(200, $unknown->getStatusCode());
        self::assertSame((string) $unknown->getBody(), (string) $known->getBody());
        self::assertCount(1, $this->mail->ofTemplate('reset_password'));

        $token = $this->mail->lastToken('reset_password');

        // A weak new password is refused and does NOT use up the link.
        $weak = $this->post('/api/v1/auth/password/reset', ['token' => $token, 'password' => 'short', 'password_confirmation' => 'short']);
        self::assertSame(422, $weak->getStatusCode());
        self::assertArrayHasKey('password', self::json($weak)['error']['fields']);

        $ok = $this->post('/api/v1/auth/password/reset', ['token' => $token, 'password' => 'brand-New-pass-7', 'password_confirmation' => 'brand-New-pass-7']);
        self::assertSame(204, $ok->getStatusCode());

        self::assertSame(401, $this->refresh($session)->getStatusCode(), 'Old session must be signed out.');
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT token_version FROM users WHERE id = ?', [$user->id]));
        self::assertSame(401, $this->login($user->email, self::PASSWORD)->getStatusCode());
        self::assertSame(200, $this->login($user->email, 'brand-New-pass-7')->getStatusCode());
        self::assertCount(1, $this->mail->ofTemplate('password_changed'));
        self::assertCount(1, $this->auditRows(AuditEvent::AUTH_PASSWORD_RESET));
    }

    public function testRegistrationValidationDuplicatesAndTurnstile(): void
    {
        $invalid = $this->post('/api/v1/auth/register', [
            'first_name' => '', 'last_name' => 'Mensah', 'email' => 'not-an-email', 'phone' => '0241234567',
            'password' => 'password', 'password_confirmation' => 'different', 'accept_terms' => false, 'turnstile_token' => 'ok',
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame(
            ['first_name', 'email', 'phone', 'password', 'password_confirmation', 'accept_terms'],
            array_keys(self::json($invalid)['error']['fields']),
        );

        self::assertSame(201, $this->register('dup@example.com')->getStatusCode());
        $duplicate = $this->register('DUP@example.com');
        self::assertSame(409, $duplicate->getStatusCode());

        $bot = $this->post('/api/v1/auth/register', ['email' => 'bot@example.com', 'turnstile_token' => 'fail']);
        self::assertSame(403, $bot->getStatusCode());
        self::assertSame('TURNSTILE_FAILED', self::json($bot)['error']['code']);
    }

    public function testCookieEndpointsRequireOriginAndXRequestedWith(): void
    {
        $cookie = $this->cookieFrom($this->login($this->activeUser()->email));

        foreach ([[], ['X-Requested-With' => 'skimr'], ['Origin' => self::ORIGIN], ['Origin' => 'https://evil.example', 'X-Requested-With' => 'skimr']] as $headers) {
            $request = $this->buildRequest('POST', '/api/v1/auth/refresh', $headers)->withCookieParams([RefreshCookie::NAME => $cookie]);
            self::assertSame(403, $this->app()->handle($request)->getStatusCode());
        }
    }

    public function testInviteAcceptSetsThePasswordActivatesAndLogsIn(): void
    {
        $tester = new \Symfony\Component\Console\Tester\CommandTester(
            $this->service(\App\Auth\Console\CreateSuperAdminCommand::class),
        );
        self::assertSame(0, $tester->execute(['--email' => 'Boss@Example.com', '--first' => 'Efua', '--last' => 'Owusu']));
        preg_match('/token=([0-9a-f]{64})/', $tester->getDisplay(), $m);
        self::assertNotEmpty($m[1] ?? null);

        // A second run with the same email is refused.
        self::assertNotSame(0, $tester->execute(['--email' => 'boss@example.com', '--first' => 'Efua', '--last' => 'Owusu']));

        $accepted = $this->post('/api/v1/auth/invite/accept', ['token' => $m[1], 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD]);
        self::assertSame(200, $accepted->getStatusCode(), (string) $accepted->getBody());
        $user = self::json($accepted)['data']['user'];
        self::assertSame('super_admin', $user['role']);
        self::assertSame('active', $user['status']);
        self::assertTrue($user['email_verified']);
        self::assertSame(400, $this->post('/api/v1/auth/invite/accept', ['token' => $m[1], 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->getStatusCode());
        self::assertSame(200, $this->login('boss@example.com')->getStatusCode());
    }

    public function testResendVerificationIsLimitedToThreePerHour(): void
    {
        $this->register('yaw@example.com');
        $token = self::json($this->login('yaw@example.com'))['data']['access_token'];

        for ($i = 0; $i < 3; $i++) {
            self::assertSame(204, $this->post('/api/v1/auth/verify-email/resend', [], ['Authorization' => 'Bearer ' . $token])->getStatusCode());
        }
        self::assertSame(429, $this->post('/api/v1/auth/verify-email/resend', [], ['Authorization' => 'Bearer ' . $token])->getStatusCode());
        self::assertCount(4, $this->mail->ofTemplate('verify_email')); // sign-up + 3 resends
    }

    public function testANewDeviceSendsASecurityEmailButTheFirstLoginDoesNot(): void
    {
        $user = $this->activeUser();

        $this->login($user->email, self::PASSWORD, '203.0.113.10');
        $this->login($user->email, self::PASSWORD, '203.0.113.10');
        self::assertCount(0, $this->mail->ofTemplate('new_sign_in'));

        $this->login($user->email, self::PASSWORD, '198.51.100.7');
        self::assertCount(1, $this->mail->ofTemplate('new_sign_in'));
    }

    public function testSuspendedUsersGetAClearErrorOnlyWithTheRightPassword(): void
    {
        $user = $this->activeUser([ 'status' => User::STATUS_SUSPENDED ]);

        self::assertSame('INVALID_CREDENTIALS', self::json($this->login($user->email, 'wrong-password-1'))['error']['code']);
        self::assertSame('ACCOUNT_SUSPENDED', self::json($this->login($user->email, self::PASSWORD))['error']['code']);
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $overrides */
    private function activeUser(array $overrides = []): User
    {
        return $this->createUser($overrides + [
            'password_hash' => $this->service(PasswordHasher::class)->hash(self::PASSWORD),
        ]);
    }

    private function register(string $email): ResponseInterface
    {
        return $this->post('/api/v1/auth/register', [
            'first_name' => 'Kofi', 'last_name' => 'Boateng', 'email' => $email,
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            'accept_terms' => true, 'turnstile_token' => 'ok',
        ]);
    }

    private function login(string $email, string $password = self::PASSWORD, string $ip = '203.0.113.10'): ResponseInterface
    {
        return $this->post('/api/v1/auth/login', ['email' => $email, 'password' => $password, 'turnstile_token' => 'ok'], [], $ip);
    }

    private function refresh(string $cookie): ResponseInterface
    {
        return $this->cookieRequest('/api/v1/auth/refresh', $cookie);
    }

    private function cookieRequest(string $path, string $cookie): ResponseInterface
    {
        $request = $this->buildRequest('POST', $path, ['Origin' => self::ORIGIN, 'X-Requested-With' => 'skimr'])
            ->withCookieParams([RefreshCookie::NAME => $cookie]);

        return $this->app()->handle($request);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function post(string $path, array $body, array $headers = [], string $ip = '203.0.113.10'): ResponseInterface
    {
        // Parsed body is what Slim's body parser would produce from the JSON.
        $request = $this->buildRequest('POST', $path, $headers, $body, $ip);

        return $this->app()->handle($request);
    }

    private function cookieFrom(ResponseInterface $response): string
    {
        $header = $response->getHeaderLine('Set-Cookie');
        self::assertMatchesRegularExpression('/^__Secure-rt=([^;]+);/', $header);
        preg_match('/^__Secure-rt=([^;]+);/', $header, $m);

        return $m[1];
    }
}
