<?php

declare(strict_types=1);

namespace App\Public\Seo;

use App\Public\Seo\JsonLd\FaqPage;
use App\Public\Seo\JsonLd\Organization;
use App\Public\Seo\JsonLd\SoftwareApplication;
use App\Public\Seo\JsonLd\WebSite;
use App\Shared\Translator;
use InvalidArgumentException;
use JsonException;

final class JsonLd
{
    private readonly string $siteUrl;

    public function __construct(
        string $siteUrl,
        private readonly string $siteName,
        private readonly Translator $translator,
    ) {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    public function forLocale(string $locale): string
    {
        $this->assertSupportedLocale($locale);

        return $this->encode($locale, []);
    }

    public function forHome(string $locale): string
    {
        $this->assertSupportedLocale($locale);
        $homeUrl = $this->siteUrl . '/' . $locale . '/';
        $faqItems = [];
        foreach (['evidence', 'sources', 'privacy', 'languages'] as $key) {
            $prefix = 'public.home.faq.items.' . $key;
            $faqItems[] = [
                'question' => $this->translator->trans($prefix . '.question', [], $locale),
                'answer' => $this->translator->trans($prefix . '.answer', [], $locale),
            ];
        }

        return $this->encode($locale, [
            SoftwareApplication::build(
                $homeUrl,
                $this->siteName,
                $locale,
                $this->translator->trans('public.home.description', [], $locale),
            ),
            FaqPage::build($homeUrl, $locale, $faqItems),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $pageSchemas
     * @throws JsonException
     */
    private function encode(string $locale, array $pageSchemas): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => array_merge([
                Organization::build($this->siteUrl, $this->siteName),
                WebSite::build($this->siteUrl, $this->siteName, $locale),
            ], $pageSchemas),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    private function assertSupportedLocale(string $locale): void
    {
        if (!Translator::isSupported($locale)) {
            throw new InvalidArgumentException('Unsupported public locale.');
        }
    }
}
