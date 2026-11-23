<?php

declare(strict_types=1);

use App\Mail\EmailBudget;
use App\Mail\EmailBudgetRepository;
use App\Mail\MailService;
use App\Shared\Clock\Clock;
use App\Shared\Mail\LogMailer;
use App\Shared\Mail\Mailer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function DI\autowire;

return [
    EmailBudget::class => autowire(EmailBudgetRepository::class),

    MailService::class => static function (ContainerInterface $container): MailService {
        /** @var array{
         *     app: array{name: string, url: string},
         *     mail: array{resend_api_key: ?string, from: ?string, reply_to: ?string},
         *     paths: array{templates: string}
         * } $settings
         */
        $settings = $container->get('settings');
        $resend = null;
        $apiKey = $settings['mail']['resend_api_key'];
        if (is_string($apiKey) && trim($apiKey) !== '') {
            $resend = \Resend::client($apiKey)->emails;
        }

        $loader = new FilesystemLoader($settings['paths']['templates'] . '/emails');
        $twig = new Environment($loader, [
            'autoescape' => static fn (string $name): string|false => str_ends_with($name, '.html.twig') ? 'html' : false,
            'strict_variables' => true,
        ]);

        return new MailService(
            $twig,
            $resend,
            $container->get(EmailBudget::class),
            $container->get(Clock::class),
            $container->get(LoggerInterface::class),
            $settings['mail']['from'],
            $settings['mail']['reply_to'],
            $settings['app']['name'],
            $settings['app']['url'],
        );
    },

    // Real delivery through Resend when RESEND_API_KEY is set. Without a key, local and
    // staging keep P1's LogMailer, so verify/reset links still land in var/mail.log for
    // testing. Production always uses MailService, which logs loudly if the key is missing.
    Mailer::class => static function (ContainerInterface $container): Mailer {
        /** @var array{app: array{env: string}, mail: array{resend_api_key: ?string}, auth: array{mail_log: string}} $settings */
        $settings = $container->get('settings');
        $key = $settings['mail']['resend_api_key'];
        if ((!is_string($key) || trim($key) === '') && $settings['app']['env'] !== 'production') {
            return new LogMailer(
                $settings['auth']['mail_log'],
                $settings['app']['env'],
                $container->get(Clock::class),
                $container->get(LoggerInterface::class),
            );
        }

        return $container->get(MailService::class);
    },
];
