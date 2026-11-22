<?php

declare(strict_types=1);

namespace App\Public\Seo;

use App\Shared\Translator;
use InvalidArgumentException;

final class MetaBuilder
{
    /** @var array<string, string> */
    private const HOME_PATHS = [
        'en' => '/en/',
        'fr' => '/fr/',
        'es' => '/es/',
    ];

    /** @var array<string, string> */
    private const OG_LOCALES = [
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'es' => 'es_ES',
    ];

    private readonly string $siteUrl;

    public function __construct(
        private readonly Translator $translator,
        string $siteUrl,
    ) {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     alternates: list<array{locale: string, url: string}>,
     *     og_locale: string
     * }
     */
    public function forHome(string $locale): array
    {
        if (!Translator::isSupported($locale)) {
            throw new InvalidArgumentException('Unsupported public locale.');
        }

        return $this->build('public.home', $locale, self::HOME_PATHS[$locale], self::HOME_PATHS);
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     alternates: list<array{locale: string, url: string}>,
     *     og_locale: string
     * }
     */
    public function forPage(string $pageKey, string $canonicalPath): array
    {
        return $this->build(
            'public.pages.' . $pageKey,
            'en',
            $canonicalPath,
            ['en' => $canonicalPath],
        );
    }

    /**
     * @param array<string, string> $alternatePaths
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     alternates: list<array{locale: string, url: string}>,
     *     og_locale: string
     * }
     */
    private function build(string $keyPrefix, string $locale, string $canonicalPath, array $alternatePaths): array
    {
        if (!str_starts_with($canonicalPath, '/') || str_starts_with($canonicalPath, '//')) {
            throw new InvalidArgumentException('Canonical paths must be local absolute paths.');
        }

        $alternatePaths['x-default'] = $alternatePaths['en']
            ?? throw new InvalidArgumentException('An English alternate URL is required.');
        $alternates = [];
        foreach ($alternatePaths as $alternateLocale => $path) {
            $alternates[] = [
                'locale' => $alternateLocale,
                'url' => $this->siteUrl . $path,
            ];
        }

        return [
            'title' => $this->translator->trans($keyPrefix . '.title', [], $locale),
            'description' => $this->translator->trans($keyPrefix . '.description', [], $locale),
            'canonical' => $this->siteUrl . $canonicalPath,
            'alternates' => $alternates,
            'og_locale' => self::OG_LOCALES[$locale],
        ];
    }
}
