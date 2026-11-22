<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Tests\AppTestCase;
use DOMDocument;

final class PublicSeoTest extends AppTestCase
{
    public function testPublicPagesUseCentralizedMetadataAndOrganizationStructuredData(): void
    {
        $paths = [
            '/en/' => 'http://localhost/en/',
            '/fr/' => 'http://localhost/fr/',
            '/es/' => 'http://localhost/es/',
            '/en/features' => 'http://localhost/en/features',
            '/en/how-it-works' => 'http://localhost/en/how-it-works',
            '/en/about' => 'http://localhost/en/about',
            '/en/privacy' => 'http://localhost/en/privacy',
            '/en/terms' => 'http://localhost/en/terms',
            '/en/cookies' => 'http://localhost/en/cookies',
        ];

        foreach ($paths as $path => $canonical) {
            $response = $this->request('GET', $path);
            $html = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString('<link rel="canonical" href="' . $canonical . '">', $html, $path);
            self::assertStringContainsString('<meta property="og:url" content="' . $canonical . '">', $html, $path);
            self::assertStringContainsString('<meta property="og:title"', $html, $path);
            self::assertStringContainsString('<meta property="og:description"', $html, $path);
            preg_match_all('/<link rel="alternate" hreflang="([^"]+)"/', $html, $alternateMatches);
            self::assertSame(
                in_array($path, ['/en/', '/fr/', '/es/'], true)
                    ? ['en', 'fr', 'es', 'x-default']
                    : ['en', 'x-default'],
                $alternateMatches[1],
                $path,
            );

            preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
            self::assertArrayHasKey(1, $matches, $path . ' is missing JSON-LD');
            $jsonLd = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($jsonLd);
            self::assertSame('https://schema.org', $jsonLd['@context']);
            $types = array_column($jsonLd['@graph'], '@type');
            self::assertContains('Organization', $types, $path);
            self::assertContains('WebSite', $types, $path);
        }
    }

    public function testRobotsFileHasRequiredRulesAndNonProductionNoindexHeader(): void
    {
        $response = $this->request('GET', '/robots.txt');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        self::assertSame(
            "User-agent: *\nAllow: /\nDisallow: /app/\nDisallow: /api/\nSitemap: http://localhost/sitemap.xml\n",
            (string) $response->getBody(),
        );
    }

    public function testSitemapIndexAndPagesContainCurrentPublicUrlsAlternatesAndLastmod(): void
    {
        $index = $this->request('GET', '/sitemap.xml');
        self::assertSame(200, $index->getStatusCode());
        self::assertSame('application/xml; charset=UTF-8', $index->getHeaderLine('Content-Type'));
        self::assertSame('public, max-age=3600', $index->getHeaderLine('Cache-Control'));

        $indexDocument = new DOMDocument();
        self::assertTrue($indexDocument->loadXML((string) $index->getBody()));
        $sitemapLocations = $indexDocument->getElementsByTagNameNS(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            'loc',
        );
        self::assertCount(1, $sitemapLocations);
        self::assertSame('http://localhost/sitemap-pages.xml', $sitemapLocations->item(0)?->textContent);

        $pages = $this->request('GET', '/sitemap-pages.xml');
        self::assertSame(200, $pages->getStatusCode());
        self::assertSame('public, max-age=3600', $pages->getHeaderLine('Cache-Control'));
        $pagesDocument = new DOMDocument();
        self::assertTrue($pagesDocument->loadXML((string) $pages->getBody()));

        $locations = $pagesDocument->getElementsByTagNameNS(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            'loc',
        );
        $lastModified = $pagesDocument->getElementsByTagNameNS(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            'lastmod',
        );
        $alternates = $pagesDocument->getElementsByTagNameNS('http://www.w3.org/1999/xhtml', 'link');

        self::assertCount(9, $locations);
        self::assertCount(9, $lastModified);
        self::assertCount(24, $alternates);
        $cachedPages = $this->request('GET', '/sitemap-pages.xml');
        self::assertSame((string) $pages->getBody(), (string) $cachedPages->getBody());
        $cachePath = dirname(__DIR__, 2) . '/var/seo/sitemap-pages-'
            . substr(hash('sha256', 'http://localhost'), 0, 16) . '.xml';
        self::assertFileExists($cachePath);
        $expiredAt = time() - 3601;
        self::assertTrue(touch($cachePath, $expiredAt));
        clearstatcache(true, $cachePath);

        $refreshedPages = $this->request('GET', '/sitemap-pages.xml');
        self::assertSame((string) $pages->getBody(), (string) $refreshedPages->getBody());
        clearstatcache(true, $cachePath);
        $refreshedAt = filemtime($cachePath);
        self::assertNotFalse($refreshedAt);
        self::assertGreaterThan($expiredAt, $refreshedAt);

        $urls = [];
        foreach ($locations as $location) {
            $urls[] = $location->textContent;
        }
        self::assertContains('http://localhost/fr/', $urls);
        self::assertContains('http://localhost/es/', $urls);
        self::assertContains('http://localhost/en/features', $urls);
        self::assertNotContains('http://localhost/fr/features', $urls);

        foreach ($lastModified as $date) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date->textContent);
        }
    }
}
