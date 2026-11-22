<?php

declare(strict_types=1);

namespace App\Public\Seo\JsonLd;

final class Organization
{
    /**
     * @return array<string, string>
     */
    public static function build(string $siteUrl, string $siteName): array
    {
        return [
            '@type' => 'Organization',
            '@id' => $siteUrl . '/#organization',
            'name' => $siteName,
            'url' => $siteUrl,
        ];
    }
}
