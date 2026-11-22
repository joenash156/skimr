<?php

declare(strict_types=1);

namespace App\Public\Seo;

use App\Shared\Translator;
use InvalidArgumentException;

final class PublicPageMap
{
    /** @var array<string, string> */
    private const HOME_PATHS = [
        'en' => '/en/',
        'fr' => '/fr/',
        'es' => '/es/',
    ];

    /**
     * @var array<string, array{en: string, fr: string, es: string}>
     */
    private const PAGE_SLUGS = [
        'features' => [
            'en' => 'features',
            'fr' => 'fonctionnalites',
            'es' => 'funciones',
        ],
        'how_it_works' => [
            'en' => 'how-it-works',
            'fr' => 'comment-ca-marche',
            'es' => 'como-funciona',
        ],
        'about' => [
            'en' => 'about',
            'fr' => 'a-propos',
            'es' => 'acerca-de',
        ],
        'contact' => [
            'en' => 'contact',
            'fr' => 'contact',
            'es' => 'contacto',
        ],
        'privacy' => [
            'en' => 'privacy',
            'fr' => 'confidentialite',
            'es' => 'privacidad',
        ],
        'terms' => [
            'en' => 'terms',
            'fr' => 'conditions-utilisation',
            'es' => 'terminos-de-uso',
        ],
        'cookies' => [
            'en' => 'cookies',
            'fr' => 'cookies',
            'es' => 'cookies',
        ],
    ];

    /** @return array<string, string> */
    public static function homePaths(): array
    {
        return self::HOME_PATHS;
    }

    /** @return list<string> */
    public static function pageKeys(): array
    {
        return array_keys(self::PAGE_SLUGS);
    }

    /** @return list<string> */
    public static function allSlugs(): array
    {
        $slugs = [];
        foreach (self::PAGE_SLUGS as $localizedSlugs) {
            foreach ($localizedSlugs as $slug) {
                $slugs[$slug] = $slug;
            }
        }

        return array_values($slugs);
    }

    public static function routePattern(): string
    {
        $quotedSlugs = array_map(
            static fn (string $slug): string => preg_quote($slug, '~'),
            self::allSlugs(),
        );

        return '(?:' . implode('|', $quotedSlugs) . ')';
    }

    public static function pageKeyFor(string $locale, string $slug): ?string
    {
        foreach (self::PAGE_SLUGS as $pageKey => $localizedSlugs) {
            if (($localizedSlugs[$locale] ?? null) === $slug) {
                return $pageKey;
            }
        }

        return null;
    }

    public static function pathForPage(string $pageKey, string $locale): string
    {
        if (!Translator::isSupported($locale)) {
            throw new InvalidArgumentException('Unsupported public locale.');
        }

        $slug = self::PAGE_SLUGS[$pageKey][$locale] ?? null;
        if ($slug === null) {
            throw new InvalidArgumentException('Unknown public page.');
        }

        return '/' . $locale . '/' . $slug;
    }

    /** @return array<string, string> locale => path */
    public static function pagePaths(string $pageKey): array
    {
        $paths = [];
        foreach (Translator::SUPPORTED as $locale) {
            $paths[$locale] = self::pathForPage($pageKey, $locale);
        }

        return $paths;
    }

    /** @return array<string, string> page key => path */
    public static function urlsForLocale(string $locale): array
    {
        if (!Translator::isSupported($locale)) {
            throw new InvalidArgumentException('Unsupported public locale.');
        }

        $urls = ['home' => self::HOME_PATHS[$locale]];
        foreach (self::PAGE_SLUGS as $pageKey => $localizedSlugs) {
            $urls[$pageKey] = '/' . $locale . '/' . $localizedSlugs[$locale];
        }

        return $urls;
    }
}
