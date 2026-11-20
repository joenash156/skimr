<?php

declare(strict_types=1);

namespace App\Shared\Mail;

use App\Shared\Clock\Clock;
use Psr\Log\LoggerInterface;

/**
 * Development stand-in for MailService: instead of sending, appends each
 * email as one JSON line to var/mail.log, so you can open the verify or reset
 * link yourself.
 *
 * The log holds working one-time links, so this mailer refuses to run in
 * production (where P2's MailService must be bound instead).
 */
final class LogMailer implements Mailer
{
    public function __construct(
        private readonly string $path,
        private readonly string $appEnv,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(string $template, string $to, string $locale, array $vars, bool $optional = false): void
    {
        if ($this->appEnv === 'production') {
            $this->logger->critical('LogMailer is bound in production; email "{template}" was NOT sent. Bind MailService.', [
                'template' => $template,
            ]);

            return;
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = json_encode([
            'at' => $this->clock->now()->format(DATE_ATOM),
            'template' => $template,
            'to' => $to,
            'locale' => $locale,
            'optional' => $optional,
            'vars' => $vars,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (@file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            // Same contract as MailService: delivery problems are logged, never thrown.
            $this->logger->error('LogMailer could not write to {path}.', ['path' => $this->path]);
        }
    }
}
