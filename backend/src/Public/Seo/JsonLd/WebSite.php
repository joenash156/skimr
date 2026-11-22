<?php

declare(strict_types=1);

namespace App\Public\Seo\JsonLd;

final class WebSite
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $siteUrl, string $siteName, string $locale): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $siteUrl . '/#website',
            'name' => $siteName,
            'url' => $siteUrl,
            'inLanguage' => $locale,
            'publisher' => [
                '@id' => $siteUrl . '/#organization',
            ],
        ];
    }
}
