<?php

declare(strict_types=1);

namespace App\Public\Seo;

use App\Shared\Translator;
use InvalidArgumentException;

final class MetaBuilder
{
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
        private readonly OgImageBuilder $ogImageBuilder,
    ) {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     og_image: string,
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

        $paths = PublicPageMap::homePaths();

        return $this->build('public.home', $locale, $paths[$locale], $paths);
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     og_image: string,
     *     canonical: string,
     *     alternates: list<array{locale: string, url: string}>,
     *     og_locale: string
     * }
     */
    public function forPage(string $pageKey, string $locale): array
    {
        if (!Translator::isSupported($locale)) {
            throw new InvalidArgumentException('Unsupported public locale.');
        }

        $paths = PublicPageMap::pagePaths($pageKey);

        return $this->build(
            'public.pages.' . $pageKey,
            $locale,
            $paths[$locale],
            $paths,
        );
    }

    /**
     * @param array<string, string> $alternatePaths
     * @return array{
     *     title: string,
     *     description: string,
     *     og_image: string,
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

        $title = $this->translator->trans($keyPrefix . '.title', [], $locale);

        return [
            'title' => $title,
            'description' => $this->translator->trans($keyPrefix . '.description', [], $locale),
            'og_image' => $this->ogImageBuilder->forTitle($title),
            'canonical' => $this->siteUrl . $canonicalPath,
            'alternates' => $alternates,
            'og_locale' => self::OG_LOCALES[$locale],
        ];
    }
}
