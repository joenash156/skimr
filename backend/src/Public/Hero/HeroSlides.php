<?php

declare(strict_types=1);

namespace App\Public\Hero;

use App\Shared\Settings\SettingsRepository;
use App\Shared\Translator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The home-page hero carousel.
 *
 * Admins manage the slides later (P2 admin settings API, P5 admin screen) by saving
 * this JSON under the settings key "public.hero_slides":
 *
 *     [
 *       {
 *         "image_url": "https://res.cloudinary.com/<cloud>/image/upload/skimr/site/hero/<id>.jpg",
 *         "heading":   {"en": "...", "fr": "...", "es": "..."},
 *         "alt":       {"en": "...", "fr": "...", "es": "..."}
 *       }
 *     ]
 *
 * Images must be Cloudinary (public delivery, spec 16 "skimr/site/...") or files under
 * /assets/, the only image hosts the CSP allows. Entries that don't match are skipped,
 * a missing language falls back to English, and with nothing valid stored the built-in
 * default slides are shown. A database problem never breaks the home page.
 */
final class HeroSlides
{
    public const SETTINGS_KEY = 'public.hero_slides';

    public const MAX_SLIDES = 6;

    private const DEFAULT_COUNT = 4;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly Translator $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return non-empty-list<HeroSlide> */
    public function forLocale(string $locale): array
    {
        $slides = [];
        foreach ($this->stored() as $entry) {
            $slide = self::fromStored($entry, $locale);
            if ($slide !== null) {
                $slides[] = $slide;
            }
            if (count($slides) === self::MAX_SLIDES) {
                break;
            }
        }

        return $slides !== [] ? $slides : $this->defaults($locale);
    }

    /** @return list<mixed> */
    private function stored(): array
    {
        try {
            $value = $this->settings->get(self::SETTINGS_KEY, []);
        } catch (Throwable $e) {
            $this->logger->warning('Hero slides could not be read; showing the defaults.', ['exception' => $e]);

            return [];
        }

        return is_array($value) ? array_values($value) : [];
    }

    private static function fromStored(mixed $entry, string $locale): ?HeroSlide
    {
        if (!is_array($entry) || !is_string($entry['image_url'] ?? null) || !self::isAllowedImage($entry['image_url'])) {
            return null;
        }

        $heading = self::localized($entry['heading'] ?? null, $locale);
        if ($heading === null) {
            return null;
        }

        return new HeroSlide($entry['image_url'], $heading, self::localized($entry['alt'] ?? null, $locale) ?? '');
    }

    private static function isAllowedImage(string $url): bool
    {
        return preg_match('~^/assets/[A-Za-z0-9/_.-]+$~', $url) === 1
            || preg_match('~^https://res\.cloudinary\.com/[A-Za-z0-9_-]+/image/upload/[^\s"\'<>]+$~', $url) === 1;
    }

    private static function localized(mixed $texts, string $locale): ?string
    {
        if (!is_array($texts)) {
            return null;
        }

        foreach ([$locale, Translator::DEFAULT] as $candidate) {
            $text = $texts[$candidate] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return mb_substr(trim($text), 0, 120);
            }
        }

        return null;
    }

    /** @return non-empty-list<HeroSlide> */
    private function defaults(string $locale): array
    {
        $slides = [];
        for ($n = 1; $n <= self::DEFAULT_COUNT; $n++) {
            $slides[] = new HeroSlide(
                '/assets/hero/slide-' . $n . '.jpg',
                $this->translator->trans('public.home.slides.' . $n . '.heading', [], $locale),
                $this->translator->trans('public.home.slides.' . $n . '.alt', [], $locale),
            );
        }

        return $slides;
    }
}
