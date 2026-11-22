<?php

declare(strict_types=1);

namespace App\Public\Seo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class SitemapController
{
    private const CACHE_TTL = 3600;

    /** @var array<string, string> */
    private const HOME_PATHS = [
        'en' => '/en/',
        'fr' => '/fr/',
        'es' => '/es/',
    ];

    /** @var list<string> */
    private const ENGLISH_PAGE_PATHS = [
        '/en/features',
        '/en/how-it-works',
        '/en/about',
        '/en/privacy',
        '/en/terms',
        '/en/cookies',
    ];

    private readonly string $siteUrl;

    public function __construct(
        string $siteUrl,
        private readonly string $cacheDirectory,
        private readonly string $templatesDirectory,
        private readonly string $translationsDirectory,
    ) {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $xml = $this->cached('index', fn (): string => $this->buildIndex());
        $response->getBody()->write($xml);

        return $response
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=' . self::CACHE_TTL);
    }

    public function pages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $xml = $this->cached('pages', fn (): string => $this->buildPages());
        $response->getBody()->write($xml);

        return $response
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=' . self::CACHE_TTL);
    }

    private function buildIndex(): string
    {
        $lastModified = $this->lastModifiedDate();
        $pagesUrl = self::escapeXml($this->siteUrl . '/sitemap-pages.xml');

        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            '  <sitemap>',
            '    <loc>' . $pagesUrl . '</loc>',
            '    <lastmod>' . $lastModified . '</lastmod>',
            '  </sitemap>',
            '</sitemapindex>',
            '',
        ]);
    }

    private function buildPages(): string
    {
        $lastModified = $this->lastModifiedDate();
        $homeAlternates = [];
        foreach (self::HOME_PATHS as $locale => $path) {
            $homeAlternates[$locale] = $this->siteUrl . $path;
        }
        $homeAlternates['x-default'] = $homeAlternates['en'];

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
        ];

        foreach (self::HOME_PATHS as $path) {
            $lines[] = $this->urlEntry($this->siteUrl . $path, $lastModified, $homeAlternates);
        }

        foreach (self::ENGLISH_PAGE_PATHS as $path) {
            $url = $this->siteUrl . $path;
            $lines[] = $this->urlEntry($url, $lastModified, [
                'en' => $url,
                'x-default' => $url,
            ]);
        }

        $lines[] = '</urlset>';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $alternates
     */
    private function urlEntry(string $url, string $lastModified, array $alternates): string
    {
        $lines = [
            '  <url>',
            '    <loc>' . self::escapeXml($url) . '</loc>',
            '    <lastmod>' . $lastModified . '</lastmod>',
        ];
        foreach ($alternates as $locale => $alternateUrl) {
            $lines[] = '    <xhtml:link rel="alternate" hreflang="' . self::escapeXml($locale)
                . '" href="' . self::escapeXml($alternateUrl) . '" />';
        }
        $lines[] = '  </url>';

        return implode("\n", $lines);
    }

    private function lastModifiedDate(): string
    {
        $files = array_merge(
            [
                $this->templatesDirectory . '/base.html.twig',
                $this->templatesDirectory . '/home.html.twig',
                $this->templatesDirectory . '/page.html.twig',
            ],
            glob($this->templatesDirectory . '/pages/*.html.twig') ?: [],
            glob($this->translationsDirectory . '/*.json') ?: [],
        );
        $lastModified = 0;
        foreach ($files as $file) {
            $modifiedAt = filemtime($file);
            if ($modifiedAt === false) {
                throw new RuntimeException(sprintf('Cannot read modification time for sitemap source %s.', $file));
            }
            $lastModified = max($lastModified, $modifiedAt);
        }

        return gmdate('Y-m-d', $lastModified);
    }

    private function cached(string $key, \Closure $build): string
    {
        if (!is_dir($this->cacheDirectory)
            && !mkdir($this->cacheDirectory, 0750, true)
            && !is_dir($this->cacheDirectory)) {
            throw new RuntimeException(sprintf('Cannot create sitemap cache directory %s.', $this->cacheDirectory));
        }

        $cacheName = sprintf('sitemap-%s-%s.xml', $key, substr(hash('sha256', $this->siteUrl), 0, 16));
        $cachePath = $this->cacheDirectory . '/' . $cacheName;
        if (is_file($cachePath)) {
            $cachedAt = filemtime($cachePath);
            if ($cachedAt === false) {
                throw new RuntimeException(sprintf('Cannot read sitemap cache timestamp for %s.', $cachePath));
            }
            if ($cachedAt <= time() && time() - $cachedAt < self::CACHE_TTL) {
                $cached = file_get_contents($cachePath);
                if ($cached === false) {
                    throw new RuntimeException(sprintf('Cannot read sitemap cache %s.', $cachePath));
                }

                return $cached;
            }
        }

        $xml = $build();
        $temporaryPath = tempnam($this->cacheDirectory, 'sitemap-');
        if ($temporaryPath === false) {
            throw new RuntimeException(sprintf('Cannot create a temporary sitemap cache file in %s.', $this->cacheDirectory));
        }
        $bytesWritten = file_put_contents($temporaryPath, $xml, LOCK_EX);
        if ($bytesWritten !== strlen($xml)) {
            unlink($temporaryPath);
            throw new RuntimeException(sprintf('Cannot write sitemap cache %s.', $cachePath));
        }
        if (!rename($temporaryPath, $cachePath)) {
            unlink($temporaryPath);
            throw new RuntimeException(sprintf('Cannot publish sitemap cache %s.', $cachePath));
        }

        return $xml;
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
