<?php

declare(strict_types=1);

namespace App\Mail;

use App\Shared\Clock\Clock;
use App\Shared\Mail\Mailer;
use Psr\Log\LoggerInterface;
use Resend\Service\Email;
use Throwable;
use Twig\Environment;

final class MailService implements Mailer
{
    private const TEMPLATES = [
        'verify_email',
        'reset_password',
        'new_sign_in',
        'password_changed',
        'suspicious_activity',
        'email_change_requested',
        'email_changed',
        'contact_message',
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly ?Email $resend,
        private readonly EmailBudget $budget,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        private readonly ?string $from,
        private readonly ?string $replyTo,
        private readonly string $appName,
        private readonly string $appUrl,
    ) {
    }

    public function send(string $template, string $to, string $locale, array $vars, bool $optional = false): void
    {
        if (!in_array($template, self::TEMPLATES, true)) {
            $this->logger->error('Email template {template} is not supported.', ['template' => $template]);

            return;
        }

        if (!in_array($locale, ['en', 'fr', 'es'], true)) {
            $this->logger->warning('Email template {template} used unsupported locale {locale}; using English.', [
                'template' => $template,
                'locale' => $locale,
            ]);
            $locale = 'en';
        }
        $renderLocale = $template === 'contact_message' ? 'en' : $locale;

        $to = trim($to);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->logger->warning('Email template {template} has an invalid recipient address.', ['template' => $template]);

            return;
        }

        if ($this->resend === null || $this->from === null || trim($this->from) === '') {
            $this->logger->error('Email delivery is not configured for template {template}.', ['template' => $template]);

            return;
        }

        $now = $this->clock->now();
        $reservedDay = null;
        try {
            $reservedDay = $this->budget->reserve($now, $optional);
        } catch (Throwable $exception) {
            $this->logger->error('Daily email budget could not be updated for {template} ({error_type}).', [
                'template' => $template,
                'error_type' => $exception::class,
            ]);
            if ($optional) {
                return;
            }
        }

        if ($optional && $reservedDay === null) {
            $this->logger->info('Optional email {template} skipped because the daily budget is used.', [
                'template' => $template,
            ]);

            return;
        }

        try {
            $vars['template'] = $template;
            $vars['locale'] = $locale;
            $vars['at'] ??= $now->format(DATE_ATOM);
            $vars['app_name'] = $this->appName;
            $vars['app_url'] = $this->appUrl;
            $vars['notification_settings_url'] = $this->appUrl . '/app/settings/notifications';

            $subject = trim($this->twig->render($renderLocale . '/subject.twig', $vars));
            if ($subject === '' || preg_match('/[\r\n]/', $subject) === 1) {
                throw new \RuntimeException('The rendered email subject is invalid.');
            }

            $payload = [
                'from' => $this->from,
                'to' => [$to],
                'subject' => $subject,
                'html' => $this->twig->render($renderLocale . '/message.html.twig', $vars),
                'text' => $this->twig->render($renderLocale . '/message.txt.twig', $vars),
            ];
            if ($this->replyTo !== null && trim($this->replyTo) !== '') {
                $payload['reply_to'] = $this->replyTo;
            }

            $this->resend->send($payload);
        } catch (Throwable $exception) {
            if ($reservedDay !== null) {
                try {
                    $this->budget->release($reservedDay, $this->clock->now());
                } catch (Throwable $releaseException) {
                    $this->logger->error('Daily email budget reservation could not be released for {template} ({error_type}).', [
                        'template' => $template,
                        'error_type' => $releaseException::class,
                    ]);
                }
            }

            $this->logger->error('Email delivery failed for template {template} ({error_type}).', [
                'template' => $template,
                'error_type' => $exception::class,
            ]);
        }
    }
}
