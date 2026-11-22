<?php

declare(strict_types=1);

namespace App\Public\Seo;

use App\Public\Seo\JsonLd\Organization;
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
    ) {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    /**
     * @throws JsonException
     */
    public function forLocale(string $locale): string
    {
        if (!Translator::isSupported($locale)) {
            throw new InvalidArgumentException('Unsupported public locale.');
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                Organization::build($this->siteUrl, $this->siteName),
                WebSite::build($this->siteUrl, $this->siteName, $locale),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}
