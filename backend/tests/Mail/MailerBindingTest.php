<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Mail\MailService;
use App\Shared\Mail\LogMailer;
use App\Shared\Mail\Mailer;
use App\Tests\AppTestCase;

/** Which Mailer the app uses depends on RESEND_API_KEY and APP_ENV (config/container/mail.php). */
final class MailerBindingTest extends AppTestCase
{
    private ?string $key = null;

    private string $env = 'local';

    public function testWithoutAKeyOutsideProductionEmailsGoToTheLogFile(): void
    {
        self::assertInstanceOf(LogMailer::class, $this->mailer());
    }

    public function testWithAKeyEmailsAreSentThroughResend(): void
    {
        $this->key = 're_test_123';

        self::assertInstanceOf(MailService::class, $this->mailer());
    }

    public function testProductionNeverFallsBackToTheLogFile(): void
    {
        $this->env = 'production';

        self::assertInstanceOf(MailService::class, $this->mailer());
    }

    private function mailer(): object
    {
        $container = $this->app()->getContainer();
        self::assertNotNull($container);

        return $container->get(Mailer::class);
    }

    /** @return array<string, mixed> */
    protected function containerOverrides(): array
    {
        $settings = require dirname(__DIR__, 2) . '/config/settings.php';
        $settings['mail']['resend_api_key'] = $this->key;
        $settings['app']['env'] = $this->env;

        return ['settings' => $settings];
    }
}
