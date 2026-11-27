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
        'admin_invite',
        'account_suspended',
        'account_reactivated',
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
        $to = trim($to);
        if (!$this->canSend($template, $to)) {
            return;
        }
        $locale = $this->supportedLocale($template, $locale);

        $now = $this->clock->now();
        $reservedDay = $this->reserve($template, $now, $optional);
        if ($optional && $reservedDay === null) {
            return;
        }

        try {
            $this->resend?->send($this->payload($template, $to, $locale, $vars, $optional, $now));
        } catch (Throwable $exception) {
            $this->release($template, $reservedDay);
            $this->logger->error('Email delivery failed for template {template} ({error_type}).', [
                'template' => $template,
                'error_type' => $exception::class,
            ]);
        }
    }

    /** Template, recipient and configuration checks; each failure is logged once. */
    private function canSend(string $template, string $to): bool
    {
        if (!in_array($template, self::TEMPLATES, true)) {
            $this->logger->error('Email template {template} is not supported.', ['template' => $template]);

            return false;
        }

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->logger->warning('Email template {template} has an invalid recipient address.', ['template' => $template]);

            return false;
        }

        if ($this->resend === null || $this->from === null || trim($this->from) === '') {
            $this->logger->error('Email delivery is not configured for template {template}.', ['template' => $template]);

            return false;
        }

        return true;
    }

    private function supportedLocale(string $template, string $locale): string
    {
        if (in_array($locale, ['en', 'fr', 'es'], true)) {
            return $locale;
        }

        $this->logger->warning('Email template {template} used unsupported locale {locale}; using English.', [
            'template' => $template,
            'locale' => $locale,
        ]);

        return 'en';
    }

    /**
     * Reserves one send in the daily budget (spec 15.3). Returns the reserved day, or null when
     * nothing was reserved: an optional email over the limit (skip it), or a storage failure
     * (security emails still go out; optional ones are skipped).
     */
    private function reserve(string $template, \DateTimeImmutable $now, bool $optional): ?string
    {
        try {
            $day = $this->budget->reserve($now, $optional);
        } catch (Throwable $exception) {
            $this->logger->error('Daily email budget could not be updated for {template} ({error_type}).', [
                'template' => $template,
                'error_type' => $exception::class,
            ]);

            return null;
        }

        if ($day === null) {
            $this->logger->info('Optional email {template} skipped because the daily budget is used.', [
                'template' => $template,
            ]);
        }

        return $day;
    }

    /** Gives a reservation back when the send failed, so a Resend outage doesn't eat the budget. */
    private function release(string $template, ?string $reservedDay): void
    {
        if ($reservedDay === null) {
            return;
        }

        try {
            $this->budget->release($reservedDay, $this->clock->now());
        } catch (Throwable $exception) {
            $this->logger->error('Daily email budget reservation could not be released for {template} ({error_type}).', [
                'template' => $template,
                'error_type' => $exception::class,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $vars
     *
     * @return array{from: string, to: list<string>, subject: string, html: string, text: string, reply_to?: string}
     */
    private function payload(string $template, string $to, string $locale, array $vars, bool $optional, \DateTimeImmutable $now): array
    {
        // The team reads contact messages, so they are always rendered in English.
        $renderLocale = $template === 'contact_message' ? 'en' : $locale;

        $vars['template'] = $template;
        $vars['locale'] = $locale;
        $vars['at'] ??= $now->format(DATE_ATOM);
        $vars['app_name'] = $this->appName;
        $vars['app_url'] = $this->appUrl;
        $vars['notification_settings_url'] = $this->appUrl . '/app/settings/notifications';
        // Only optional emails link to notification settings; security emails can't be turned off (spec 15.2).
        $vars['optional'] = $optional;

        $subject = trim($this->twig->render($renderLocale . '/subject.twig', $vars));
        if ($subject === '' || preg_match('/[
]/', $subject) === 1) {
            throw new InvalidEmailException('The rendered email subject is invalid.');
        }

        $payload = [
            'from' => (string) $this->from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $this->twig->render($renderLocale . '/message.html.twig', $vars),
            'text' => $this->twig->render($renderLocale . '/message.txt.twig', $vars),
        ];

        $replyTo = $this->replyTo;
        // Contact form: pressing Reply should answer the visitor, not the team inbox itself.
        $visitor = $template === 'contact_message' && is_string($vars['email'] ?? null) ? trim($vars['email']) : '';
        if ($visitor !== '' && filter_var($visitor, FILTER_VALIDATE_EMAIL) !== false) {
            $replyTo = $visitor;
        }
        if ($replyTo !== null && trim($replyTo) !== '') {
            $payload['reply_to'] = $replyTo;
        }

        return $payload;
    }
}
