<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Mail\Mailer;

/** Test double: remembers every email instead of sending it. */
final class SpyMailer implements Mailer
{
    /** @var list<array{template: string, to: string, locale: string, vars: array<string, mixed>, optional: bool}> */
    public array $sent = [];

    public function send(string $template, string $to, string $locale, array $vars, bool $optional = false): void
    {
        $this->sent[] = ['template' => $template, 'to' => $to, 'locale' => $locale, 'vars' => $vars, 'optional' => $optional];
    }

    /** @return list<array{template: string, to: string, locale: string, vars: array<string, mixed>, optional: bool}> */
    public function ofTemplate(string $template): array
    {
        return array_values(array_filter($this->sent, static fn (array $m): bool => $m['template'] === $template));
    }

    /** The ?token= value from the link in the most recent email of this template. */
    public function lastToken(string $template): string
    {
        $mails = $this->ofTemplate($template);
        if ($mails === []) {
            throw new \RuntimeException('No "' . $template . '" email was sent.');
        }
        $link = (string) ($mails[array_key_last($mails)]['vars']['link'] ?? '');
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        return is_string($query['token'] ?? null) ? $query['token'] : throw new \RuntimeException('No token in ' . $link);
    }
}
