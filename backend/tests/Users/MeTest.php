<?php

declare(strict_types=1);

namespace App\Tests\Users;

use App\Auth\Jwt\JwtService;
use App\Auth\Password\PasswordHasher;
use App\Security\Audit\AuditEvent;
use App\Security\Turnstile\TurnstileVerifier;
use App\Shared\Clock\Clock;
use App\Shared\Clock\FrozenClock;
use App\Shared\Mail\Mailer;
use App\Shared\Settings\FeatureFlags;
use App\Shared\Settings\SettingsRepository;
use App\Tests\DatabaseTestCase;
use App\Tests\Support\FakeTurnstile;
use App\Tests\Support\SpyMailer;
use App\Users\User;
use App\Users\UserRepository;
use Psr\Http\Message\ResponseInterface;

/** The /me endpoints (P1 brief B2 task 2). */
final class MeTest extends DatabaseTestCase
{
    private const PASSWORD = 'river-Lantern-42';
    private const NEW_PASSWORD = 'brand-New-pass-7';

    private SpyMailer $mail;

    protected function setUp(): void
    {
        $this->mail = new SpyMailer();
        parent::setUp();
    }

    protected function containerOverrides(): array
    {
        return [
            Mailer::class => $this->mail,
            Clock::class => new FrozenClock('2026-11-20 10:00:00'),
            TurnstileVerifier::class => FakeTurnstile::verifier(),
        ];
    }

    public function testGetMeReturnsProfileDefaultsAndFeatureFlags(): void
    {
        $user = $this->activeUser();
        $this->service(SettingsRepository::class)->set(FeatureFlags::SETTINGS_KEY, ['upload_images' => true, 'chat' => 'yes']);

        $response = $this->call('GET', '/api/v1/me', $user);

        self::assertSame(200, $response->getStatusCode());
        $me = self::json($response)['data'];
        self::assertSame($user->uuid, $me['id']);
        self::assertTrue($me['has_password']);
        self::assertSame(['processing_done' => true, 'report_ready' => true, 'project_invite' => true, 'weekly_digest' => false], $me['notification_prefs']);
        // Only a real `true` enables a flag; unset flags are false.
        self::assertSame(['upload_images' => true, 'add_link' => false, 'paste_text' => false, 'discover' => false, 'chat' => false], $me['features']);
        self::assertArrayNotHasKey('password_hash', $me);
    }

    public function testPendingUsersMayReadMeButNotChangeIt(): void
    {
        $pending = $this->activeUser(['status' => User::STATUS_PENDING_VERIFICATION, 'email_verified_at' => null]);

        self::assertSame(200, $this->call('GET', '/api/v1/me', $pending)->getStatusCode());
        $patch = $this->call('PATCH', '/api/v1/me', $pending, ['theme' => 'dark']);
        self::assertSame(403, $patch->getStatusCode());
        self::assertSame('EMAIL_NOT_VERIFIED', self::json($patch)['error']['code']);
    }

    public function testPatchUpdatesOnlyTheFieldsSentAndMergesNotificationPrefs(): void
    {
        $user = $this->activeUser(['institution' => 'University of Ghana', 'notification_prefs' => '{"report_ready": false}']);

        $response = $this->call('PATCH', '/api/v1/me', $user, [
            'first_name' => '  Akosua ',
            'phone' => '+233241234567',
            'other_names' => null,
            'theme' => 'dark',
            'locale' => 'es',
            'notification_prefs' => ['weekly_digest' => true],
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $me = self::json($response)['data'];
        self::assertSame('Akosua', $me['first_name']);
        self::assertSame('Mensah', $me['last_name'], 'Fields not sent stay as they were.');
        self::assertSame('University of Ghana', $me['institution']);
        self::assertSame('+233241234567', $me['phone']);
        self::assertSame('dark', $me['theme']);
        self::assertSame('es', $me['locale']);
        self::assertFalse($me['notification_prefs']['report_ready'], 'Earlier changes are kept.');
        self::assertTrue($me['notification_prefs']['weekly_digest']);
    }

    public function testPatchRejectsBadValuesUnknownFieldsAndEmptyBodies(): void
    {
        $user = $this->activeUser();

        $bad = $this->call('PATCH', '/api/v1/me', $user, [
            'first_name' => '', 'phone' => '024 123', 'theme' => 'neon', 'notification_prefs' => ['security_alerts' => false],
        ]);
        self::assertSame(422, $bad->getStatusCode());
        self::assertSame(['first_name', 'phone', 'theme', 'notification_prefs'], array_keys(self::json($bad)['error']['fields']));

        // Email, role and status can't be changed here.
        $sneaky = $this->call('PATCH', '/api/v1/me', $user, ['role' => 'super_admin', 'email' => 'x@example.com']);
        self::assertSame(422, $sneaky->getStatusCode());
        self::assertSame(['role', 'email'], array_keys(self::json($sneaky)['error']['fields']));
        self::assertSame('user', $this->service(UserRepository::class)->findById($user->id)?->role);

        self::assertSame(422, $this->call('PATCH', '/api/v1/me', $user, [])->getStatusCode());
    }

    public function testChangePasswordNeedsTheCurrentOneAndSignsOutEverywhere(): void
    {
        $user = $this->activeUser();

        $wrong = $this->call('POST', '/api/v1/me/password', $user, [
            'current_password' => 'not-the-password-1', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ]);
        self::assertSame(401, $wrong->getStatusCode());
        self::assertSame('INVALID_CREDENTIALS', self::json($wrong)['error']['code']);

        $weak = $this->call('POST', '/api/v1/me/password', $user, [
            'current_password' => self::PASSWORD, 'password' => 'short', 'password_confirmation' => 'short',
        ]);
        self::assertSame(422, $weak->getStatusCode());

        $token = $this->bearer($user);
        $ok = $this->call('POST', '/api/v1/me/password', $user, [
            'current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ], $token);
        self::assertSame(204, $ok->getStatusCode());
        self::assertStringContainsString('Max-Age=0', $ok->getHeaderLine('Set-Cookie'));

        // The old access token stops working at once (token_version bumped).
        self::assertSame(401, $this->call('GET', '/api/v1/me', $user, null, $token)->getStatusCode());
        $fresh = $this->service(UserRepository::class)->findById($user->id);
        self::assertNotNull($fresh);
        self::assertTrue($this->service(PasswordHasher::class)->verify(self::NEW_PASSWORD, $fresh->passwordHash));
        self::assertCount(1, $this->mail->ofTemplate('password_changed'));
        self::assertCount(1, $this->auditRows(AuditEvent::USER_PASSWORD_CHANGED));
    }

    public function testWrongPasswordGuessesOnMeEndpointsAreLimited(): void
    {
        $user = $this->activeUser();
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(401, $this->call('DELETE', '/api/v1/me', $user, ['password' => 'guess-number-' . $i])->getStatusCode());
        }

        // The 6th try is refused even with the right password.
        $limited = $this->call('DELETE', '/api/v1/me', $user, ['password' => self::PASSWORD]);
        self::assertSame(429, $limited->getStatusCode());
        self::assertSame(User::STATUS_ACTIVE, $this->service(UserRepository::class)->findById($user->id)?->status);
    }

    public function testEmailChangeVerifiesTheNewAddressAndNotifiesTheOldOne(): void
    {
        $user = $this->activeUser();
        $taken = $this->activeUser();

        self::assertSame(409, $this->call('POST', '/api/v1/me/email', $user, ['email' => $taken->email, 'password' => self::PASSWORD])->getStatusCode());
        self::assertSame(401, $this->call('POST', '/api/v1/me/email', $user, ['email' => 'new@example.com', 'password' => 'wrong-password-1'])->getStatusCode());

        $requested = $this->call('POST', '/api/v1/me/email', $user, ['email' => 'New@Example.com', 'password' => self::PASSWORD]);
        self::assertSame(202, $requested->getStatusCode());
        self::assertSame($user->email, $this->service(UserRepository::class)->findById($user->id)?->email, 'Nothing changes before the link is opened.');

        $verify = $this->mail->ofTemplate('verify_email');
        self::assertSame('new@example.com', $verify[0]['to']);
        $notice = $this->mail->ofTemplate('email_change_requested');
        self::assertSame($user->email, $notice[0]['to']);

        // The link from the new inbox lands on the normal verify page.
        $confirm = $this->request('POST', '/api/v1/auth/verify-email', [], ['token' => $this->mail->lastToken('verify_email')]);
        self::assertSame(200, $confirm->getStatusCode(), (string) $confirm->getBody());
        self::assertSame('new@example.com', self::json($confirm)['data']['user']['email']);
        self::assertSame($user->email, $this->mail->ofTemplate('email_changed')[0]['to'], 'The old address is told.');
        self::assertCount(1, $this->auditRows(AuditEvent::USER_EMAIL_CHANGED));
    }

    public function testDeleteSoftDeletesAndSignsOutEverywhere(): void
    {
        $user = $this->activeUser();
        $token = $this->bearer($user);

        $deleted = $this->call('DELETE', '/api/v1/me', $user, ['password' => self::PASSWORD], $token);

        self::assertSame(204, $deleted->getStatusCode());
        $row = $this->db()->fetchAssociative('SELECT status, deleted_at FROM users WHERE id = ?', [$user->id]);
        self::assertSame('deleted', $row['status'] ?? null);
        self::assertNotNull($row['deleted_at'] ?? null);
        self::assertSame(401, $this->call('GET', '/api/v1/me', $user, null, $token)->getStatusCode());
        self::assertNull($this->service(UserRepository::class)->findByEmail($user->email));
        self::assertCount(1, $this->auditRows(AuditEvent::USER_ACCOUNT_DELETED));
    }

    public function testGoogleOnlyAccountsMustSetAPasswordFirst(): void
    {
        $user = $this->createUser(['google_sub' => 'g-1', 'password_hash' => null]);

        $response = $this->call('DELETE', '/api/v1/me', $user, ['password' => 'anything-at-all-1']);
        self::assertSame(401, $response->getStatusCode());
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $overrides */
    private function activeUser(array $overrides = []): User
    {
        return $this->createUser($overrides + ['password_hash' => $this->service(PasswordHasher::class)->hash(self::PASSWORD)]);
    }

    private function bearer(User $user): string
    {
        return 'Bearer ' . $this->service(JwtService::class)->issueAccessToken($user)->token;
    }

    /** @param array<string, mixed>|null $body */
    private function call(string $method, string $path, User $user, ?array $body = null, ?string $authorization = null): ResponseInterface
    {
        return $this->request($method, $path, ['Authorization' => $authorization ?? $this->bearer($user)], $body);
    }
}
