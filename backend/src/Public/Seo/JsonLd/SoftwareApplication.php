<?php

declare(strict_types=1);

namespace App\Public\Seo\JsonLd;

final class SoftwareApplication
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $url, string $name, string $locale, string $description): array
    {
        return [
            '@type' => 'SoftwareApplication',
            '@id' => $url . '#softwareapplication',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'applicationCategory' => 'EducationalApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => $locale,
            'offers' => [
                '@type' => 'Offer',
                'price' => 0,
            ],
        ];
    }
}
