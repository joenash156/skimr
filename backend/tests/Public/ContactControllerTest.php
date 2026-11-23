<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Public\Contact\ContactMessage;
use App\Public\Contact\ContactMessageRepository;
use App\Public\Contact\ContactMessageService;
use App\Security\Turnstile\TurnstileVerifier;
use App\Shared\Clock\Clock;
use App\Shared\Clock\FrozenClock;
use App\Shared\Mail\Mailer;
use App\Tests\DatabaseTestCase;
use App\Tests\Support\FakeTurnstile;
use App\Tests\Support\SpyMailer;
use Psr\Log\NullLogger;

final class ContactControllerTest extends DatabaseTestCase
{
    private SpyMailer $mailer;

    protected function setUp(): void
    {
        $this->mailer = new SpyMailer();
        parent::setUp();
        // The real table, created by the migration that composer test runs first.
        $this->db()->executeStatement('DELETE FROM contact_messages');
    }

    /**
     * @return array<string, mixed>
     */
    protected function containerOverrides(): array
    {
        $settings = require dirname(__DIR__, 2) . '/config/settings.php';
        $settings['mail']['reply_to'] = 'team@example.test';

        return [
            'settings' => $settings,
            Clock::class => new FrozenClock('2026-11-22 09:30:00'),
            Mailer::class => $this->mailer,
            TurnstileVerifier::class => FakeTurnstile::verifier(),
        ];
    }

    public function testStoresMessageAndEmailsTheTeamInEnglish(): void
    {
        $response = $this->request('POST', '/api/v1/contact', [], [
            'name' => 'Ama Mensah',
            'email' => 'AMA@example.com',
            'message' => 'Does Skimr support reviews in French?',
            'locale' => 'fr',
            'turnstile_token' => 'valid-token',
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(
            'Merci, votre message a été envoyé. Nous vous répondrons par e-mail.',
            self::json($response)['data']['message'],
        );
        self::assertSame([
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'message' => 'Does Skimr support reviews in French?',
            'ip' => inet_pton('203.0.113.10'),
        ], $this->db()->fetchAssociative(
            'SELECT name, email, message, ip FROM contact_messages',
        ));
        self::assertSame([[
            'template' => 'contact_message',
            'to' => 'team@example.test',
            'locale' => 'en',
            'vars' => [
                'name' => 'Ama Mensah',
                'email' => 'ama@example.com',
                'message' => 'Does Skimr support reviews in French?',
                'locale' => 'fr',
                'at' => '2026-11-22T09:30:00+00:00',
            ],
            'optional' => false,
        ]], $this->mailer->sent);
    }

    public function testReturnsLocalizedValidationErrorsWithoutStoringOrEmailing(): void
    {
        $response = $this->request('POST', '/api/v1/contact', ['Accept-Language' => 'fr'], [
            'name' => 'Ama Mensah',
            'email' => 'not-an-email',
            'message' => 'Does Skimr support reviews in French?',
            'locale' => 'fr',
            'turnstile_token' => 'valid-token',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $body = self::json($response);
        self::assertSame('Veuillez vérifier les champs signalés.', $body['error']['message']);
        self::assertSame("Cette adresse e-mail n'est pas valide.", $body['error']['fields']['email']);
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM contact_messages'));
        self::assertSame([], $this->mailer->sent);
    }

    public function testRejectsFailedTurnstileBeforeStoringOrEmailing(): void
    {
        $response = $this->request('POST', '/api/v1/contact', [], [
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'message' => 'Does Skimr support reviews in French?',
            'turnstile_token' => 'fail',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('TURNSTILE_FAILED', self::json($response)['error']['code']);
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM contact_messages'));
        self::assertSame([], $this->mailer->sent);
    }

    public function testLimitsToThreeMessagesPerHourPerIp(): void
    {
        $body = [
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'message' => 'Does Skimr support reviews in French?',
            'turnstile_token' => 'valid-token',
        ];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            self::assertSame(201, $this->request('POST', '/api/v1/contact', [], $body)->getStatusCode());
        }

        $limited = $this->request('POST', '/api/v1/contact', [], $body);
        self::assertSame(429, $limited->getStatusCode());
        self::assertSame('RATE_LIMITED', self::json($limited)['error']['code']);
        self::assertNotSame('', $limited->getHeaderLine('Retry-After'));
        self::assertSame(3, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM contact_messages'));
        self::assertCount(3, $this->mailer->sent);
    }

    public function testStillStoresTheMessageWhenNoTeamInboxIsConfigured(): void
    {
        $service = new ContactMessageService(
            $this->service(ContactMessageRepository::class),
            $this->mailer,
            new FrozenClock('2026-11-22 09:30:00'),
            new NullLogger(),
            null,
        );

        $service->send(new ContactMessage('Ama Mensah', 'ama@example.com', 'Does Skimr support French?', null), 'en');

        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM contact_messages'));
        self::assertSame([], $this->mailer->sent);
    }
}
