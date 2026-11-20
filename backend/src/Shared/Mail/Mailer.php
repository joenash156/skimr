<?php

declare(strict_types=1);

namespace App\Shared\Mail;

/**
 * Sends one templated email (spec 15). Auth and other modules depend on this
 * interface only.
 *
 * Until P2's App\Mail\MailService exists, the container binds this to
 * LogMailer. P2 switches the binding in config/container/mail.php:
 *
 *     Mailer::class => DI\get(MailService::class),
 *
 * Implementations render templates/emails/{locale}/{template}, send through
 * Resend, and log and swallow delivery failures (P2 brief B0 task 3).
 */
interface Mailer
{
    /**
     * @param string $template template name, e.g. 'verify_email', 'reset_password', 'new_sign_in'
     * @param string $to recipient address
     * @param string $locale 'en' | 'fr' | 'es'
     * @param array<string, mixed> $vars template variables (links, names...)
     * @param bool $optional true for nice-to-have emails, skipped once the daily budget is used (spec 15.3)
     */
    public function send(string $template, string $to, string $locale, array $vars, bool $optional = false): void;
}
