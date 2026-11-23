<?php

declare(strict_types=1);

use App\Mail\EmailBudget;
use App\Mail\EmailBudgetRepository;
use App\Mail\MailService;
use App\Shared\Clock\Clock;
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

    Mailer::class => static fn (ContainerInterface $container): Mailer => $container->get(MailService::class),
];
