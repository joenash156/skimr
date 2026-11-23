<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Mail\EmailBudget;
use App\Mail\MailService;
use App\Shared\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Resend\Email as ResendEmail;
use Resend\Service\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class MailServiceTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock('2026-11-20 10:00:00');
    }

    public function testRendersLocalizedHtmlAndTextAndEscapesHtmlVariables(): void
    {
        $budget = $this->createMock(EmailBudget::class);
        $budget->expects(self::once())
            ->method('reserve')
            ->with($this->clock->now(), false)
            ->willReturn('2026-11-20');
        $sender = $this->createMock(Email::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (array $payload): bool {
                self::assertSame(['ama@example.com'], $payload['to']);
                self::assertSame('team@example.com', $payload['reply_to']);
                self::assertStringContainsString('Confirmez votre adresse e-mail', $payload['subject']);
                self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $payload['html']);
                self::assertStringContainsString('token=a=1&amp;b=2', $payload['html']);
                self::assertStringContainsString('<script>alert(1)</script>', $payload['text']);
                self::assertStringContainsString('https://example.test/app/settings/notifications', $payload['text']);

                return true;
            }))
            ->willReturn(self::createStub(ResendEmail::class));

        $service = $this->service($sender, $budget, $this->createMock(LoggerInterface::class));
        $service->send('verify_email', 'ama@example.com', 'fr', [
            'first_name' => '<script>alert(1)</script>',
            'link' => 'https://example.test/verify?token=a=1&b=2',
            'expires_hours' => 24,
        ]);
    }

    public function testEverySupportedTemplateRendersInEachLocale(): void
    {
        $templates = [
            'verify_email',
            'reset_password',
            'new_sign_in',
            'password_changed',
            'suspicious_activity',
            'email_change_requested',
            'email_changed',
            'contact_message',
        ];
        $budget = $this->createMock(EmailBudget::class);
        $budget->expects(self::exactly(count($templates) * 3))
            ->method('reserve')
            ->willReturn('2026-11-20');
        $sender = $this->createMock(Email::class);
        $sender->expects(self::exactly(count($templates) * 3))
            ->method('send')
            ->willReturn(self::createStub(ResendEmail::class));
        $service = $this->service($sender, $budget, $this->createMock(LoggerInterface::class));
        $vars = [
            'first_name' => 'Ama',
            'link' => 'https://example.test/action',
            'expires_hours' => 24,
            'expires_minutes' => 60,
            'at' => '2026-11-20T10:00:00+00:00',
            'ip' => '203.0.113.10',
            'user_agent' => 'Test browser',
            'new_email' => 'new@example.com',
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => "Hello\nSkimr",
        ];

        foreach (['en', 'fr', 'es'] as $locale) {
            foreach ($templates as $template) {
                $service->send($template, 'recipient@example.com', $locale, $vars);
            }
        }
    }

    public function testContactMessageUsesEnglishAndIncludesVisitorLocale(): void
    {
        $budget = $this->createMock(EmailBudget::class);
        $budget->expects(self::once())->method('reserve')->willReturn('2026-11-20');
        $sender = $this->createMock(Email::class);
        $sender->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (array $payload): bool {
                self::assertStringContainsString('New contact message', $payload['subject']);
                self::assertStringContainsString('Visitor language:</strong> fr', $payload['html']);
                self::assertStringContainsString('Visitor language: fr', $payload['text']);
                self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $payload['html']);
                self::assertStringContainsString('<script>alert(1)</script>', $payload['text']);

                return true;
            }))
            ->willReturn(self::createStub(ResendEmail::class));

        $this->service($sender, $budget, $this->createMock(LoggerInterface::class))->send(
            'contact_message',
            'team@example.com',
            'fr',
            ['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => '<script>alert(1)</script>'],
        );
    }

    public function testSkipsOptionalEmailWhenBudgetIsExhausted(): void
    {
        $budget = $this->createMock(EmailBudget::class);
        $budget->expects(self::once())
            ->method('reserve')
            ->with($this->clock->now(), true)
            ->willReturn(null);
        $sender = $this->createMock(Email::class);
        $sender->expects(self::never())->method('send');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $this->service($sender, $budget, $logger)->send(
            'verify_email',
            'ama@example.com',
            'en',
            ['first_name' => 'Ama', 'link' => 'https://example.test', 'expires_hours' => 24],
            true,
        );
    }

    public function testReleasesReservationAndLogsWhenResendFails(): void
    {
        $budget = $this->createMock(EmailBudget::class);
        $budget->expects(self::once())->method('reserve')->willReturn('2026-11-20');
        $budget->expects(self::once())->method('release')->with('2026-11-20', $this->clock->now());
        $sender = $this->createMock(Email::class);
        $sender->expects(self::once())->method('send')->willThrowException(new \RuntimeException('private payload'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Email delivery failed for template {template} ({error_type}).',
                self::callback(static function (array $context): bool {
                    self::assertSame('verify_email', $context['template']);
                    self::assertSame(\RuntimeException::class, $context['error_type']);
                    self::assertArrayNotHasKey('exception', $context);

                    return true;
                }),
            );

        $this->service($sender, $budget, $logger)->send(
            'verify_email',
            'ama@example.com',
            'en',
            ['first_name' => 'Ama', 'link' => 'https://example.test', 'expires_hours' => 24],
        );
    }

    public function testMandatoryEmailStillSendsIfBudgetStorageFails(): void
    {
        $budget = $this->createMock(EmailBudget::class);
        $budget->expects(self::once())
            ->method('reserve')
            ->willThrowException(new \RuntimeException('private database details'));
        $sender = $this->createMock(Email::class);
        $sender->expects(self::once())->method('send')->willReturn(self::createStub(ResendEmail::class));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->service($sender, $budget, $logger)->send(
            'verify_email',
            'ama@example.com',
            'en',
            ['first_name' => 'Ama', 'link' => 'https://example.test', 'expires_hours' => 24],
        );
    }

    public function testConfigurationFailureIsLoggedWithoutAttemptingQuotaOrDelivery(): void
    {
        $budget = $this->createMock(EmailBudget::class);
        $budget->expects(self::never())->method('reserve');
        $sender = $this->createMock(Email::class);
        $sender->expects(self::never())->method('send');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->service($sender, $budget, $logger, from: null)->send(
            'verify_email',
            'ama@example.com',
            'en',
            ['first_name' => 'Ama', 'link' => 'https://example.test', 'expires_hours' => 24],
        );
    }

    private function service(
        ?Email $sender,
        EmailBudget $budget,
        LoggerInterface $logger,
        ?string $from = 'Skimr <no-reply@example.com>',
    ): MailService {
        $twig = new Environment(
            new FilesystemLoader(dirname(__DIR__, 2) . '/templates/emails'),
            [
                'autoescape' => static fn (string $name): string|false => str_ends_with($name, '.html.twig') ? 'html' : false,
                'strict_variables' => true,
            ],
        );

        return new MailService(
            $twig,
            $sender,
            $budget,
            $this->clock,
            $logger,
            $from,
            'team@example.com',
            'Skimr',
            'https://example.test',
        );
    }
}
