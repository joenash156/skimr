<?php

declare(strict_types=1);

namespace App\Public\Site;

use App\Shared\Settings\SettingsRepository;
use App\Shared\Translator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The public site's contact details and social links (footer, contact page).
 *
 * Admins edit them later (P2 admin settings API, P5 super-admin screen) by saving
 * this JSON under the settings key "public.site_profile":
 *
 *     {
 *       "email":   "hello@example.org",
 *       "phone":   "+233 20 000 0000",
 *       "address": {"en": "...", "fr": "...", "es": "..."},
 *       "socials": {"x": "https://x.com/...", "linkedin": "https://www.linkedin.com/company/...",
 *                   "github": "https://github.com/...", "instagram": "...", "facebook": "...", "youtube": "..."}
 *     }
 *
 * Every field is optional and validated here: a bad value is dropped, never shown.
 * Social links must be https and on that network's own domain. Without a stored
 * email, the team inbox (MAIL_REPLY_TO) is shown. A database problem never breaks a page.
 */
final class SiteProfiles
{
    public const SETTINGS_KEY = 'public.site_profile';

    /** network => allowed hosts (with or without "www."). Order = display order. */
    public const NETWORKS = [
        'linkedin' => ['linkedin.com'],
        'x' => ['x.com', 'twitter.com'],
        'github' => ['github.com'],
        'instagram' => ['instagram.com'],
        'facebook' => ['facebook.com'],
        'youtube' => ['youtube.com'],
    ];

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly LoggerInterface $logger,
        private readonly ?string $fallbackEmail,
    ) {
    }

    public function forLocale(string $locale): SiteProfile
    {
        $data = $this->stored();

        return new SiteProfile(
            self::email($data['email'] ?? null) ?? self::email($this->fallbackEmail),
            self::phone($data['phone'] ?? null),
            self::localized($data['address'] ?? null, $locale),
            self::socials($data['socials'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        if ($this->stored !== null) {
            return $this->stored;
        }

        try {
            $value = $this->settings->get(self::SETTINGS_KEY, []);
        } catch (Throwable $e) {
            $this->logger->warning('Site profile could not be read; showing defaults.', ['exception' => $e]);
            $value = [];
        }

        return $this->stored = is_array($value) ? $value : [];
    }

    private static function email(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        // MAIL_REPLY_TO may be written as "Team <team@example.org>".
        if (preg_match('/<([^>]+)>/', $value, $match) === 1) {
            $value = $match[1];
        }
        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    }

    private static function phone(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return preg_match('/^\+?[0-9][0-9 ()\-.]{5,24}$/', $value) === 1 ? $value : null;
    }

    private static function localized(mixed $texts, string $locale): ?string
    {
        if (!is_array($texts)) {
            return null;
        }
        foreach ([$locale, Translator::DEFAULT] as $candidate) {
            $text = $texts[$candidate] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return mb_substr(trim($text), 0, 200);
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private static function socials(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $links = [];
        foreach (self::NETWORKS as $network => $hosts) {
            $url = $value[$network] ?? null;
            if (is_string($url) && self::isNetworkUrl(trim($url), $hosts)) {
                $links[$network] = trim($url);
            }
        }

        return $links;
    }

    /** @param list<string> $hosts */
    private static function isNetworkUrl(string $url, array $hosts): bool
    {
        if (strlen($url) > 300 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return in_array($host, $hosts, true);
    }
}
