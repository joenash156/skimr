<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Shared\Translator;
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
            '/fr/fonctionnalites' => 'http://localhost/fr/fonctionnalites',
            '/es/funciones' => 'http://localhost/es/funciones',
            '/en/how-it-works' => 'http://localhost/en/how-it-works',
            '/fr/comment-ca-marche' => 'http://localhost/fr/comment-ca-marche',
            '/es/como-funciona' => 'http://localhost/es/como-funciona',
            '/en/about' => 'http://localhost/en/about',
            '/fr/a-propos' => 'http://localhost/fr/a-propos',
            '/es/acerca-de' => 'http://localhost/es/acerca-de',
            '/en/contact' => 'http://localhost/en/contact',
            '/fr/contact' => 'http://localhost/fr/contact',
            '/es/contacto' => 'http://localhost/es/contacto',
            '/en/privacy' => 'http://localhost/en/privacy',
            '/fr/confidentialite' => 'http://localhost/fr/confidentialite',
            '/es/privacidad' => 'http://localhost/es/privacidad',
            '/en/terms' => 'http://localhost/en/terms',
            '/fr/conditions-utilisation' => 'http://localhost/fr/conditions-utilisation',
            '/es/terminos-de-uso' => 'http://localhost/es/terminos-de-uso',
            '/en/cookies' => 'http://localhost/en/cookies',
            '/fr/cookies' => 'http://localhost/fr/cookies',
            '/es/cookies' => 'http://localhost/es/cookies',
        ];

        foreach ($paths as $path => $canonical) {
            $response = $this->request('GET', $path);
            $html = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString('<link rel="canonical" href="' . $canonical . '">', $html, $path);
            self::assertStringContainsString('<meta property="og:url" content="' . $canonical . '">', $html, $path);
            self::assertStringContainsString('<meta property="og:title"', $html, $path);
            self::assertStringContainsString('<meta property="og:description"', $html, $path);
            self::assertStringContainsString(
                '<meta property="og:image" content="http://localhost/assets/og-default.svg">',
                $html,
                $path,
            );
            self::assertStringContainsString('<meta property="og:image:width" content="1200">', $html, $path);
            self::assertStringContainsString('<meta property="og:image:height" content="630">', $html, $path);
            preg_match('/<meta property="og:title" content="([^"]*)">/', $html, $ogTitle);
            preg_match('/<meta property="og:image:alt" content="([^"]*)">/', $html, $ogImageAlt);
            self::assertArrayHasKey(1, $ogTitle, $path . ' is missing an Open Graph title');
            self::assertArrayHasKey(1, $ogImageAlt, $path . ' is missing Open Graph image alt text');
            self::assertSame($ogTitle[1], $ogImageAlt[1], $path . ' has mismatched Open Graph image alt text');
            preg_match_all('/<link rel="alternate" hreflang="([^"]+)"/', $html, $alternateMatches);
            self::assertSame(['en', 'fr', 'es', 'x-default'], $alternateMatches[1], $path);

            preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
            self::assertArrayHasKey(1, $matches, $path . ' is missing JSON-LD');
            $jsonLd = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($jsonLd);
            self::assertSame('https://schema.org', $jsonLd['@context']);
            $types = array_column($jsonLd['@graph'], '@type');
            self::assertContains('Organization', $types, $path);
            self::assertContains('WebSite', $types, $path);
            if (in_array($path, ['/en/', '/fr/', '/es/'], true)) {
                self::assertContains('SoftwareApplication', $types, $path);
                self::assertContains('FAQPage', $types, $path);
            } else {
                self::assertNotContains('SoftwareApplication', $types, $path);
                self::assertNotContains('FAQPage', $types, $path);
            }
        }
    }

    public function testHomeJsonLdContainsLocalizedApplicationAndVisibleFaqData(): void
    {
        $translator = new Translator(dirname(__DIR__, 2) . '/translations/public');
        $faqKeys = ['evidence', 'sources', 'privacy', 'languages'];

        foreach (Translator::SUPPORTED as $locale) {
            $response = $this->request('GET', '/' . $locale . '/');
            preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', (string) $response->getBody(), $matches);
            self::assertArrayHasKey(1, $matches);
            $jsonLd = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($jsonLd['@graph']);
            $graph = $jsonLd['@graph'];

            $applicationIndex = array_search('SoftwareApplication', array_column($graph, '@type'), true);
            self::assertNotFalse($applicationIndex);
            $application = $graph[$applicationIndex];
            self::assertSame('EducationalApplication', $application['applicationCategory']);
            self::assertSame($locale, $application['inLanguage']);
            self::assertSame(0, $application['offers']['price']);

            $faqIndex = array_search('FAQPage', array_column($graph, '@type'), true);
            self::assertNotFalse($faqIndex);
            $faq = $graph[$faqIndex];
            self::assertSame($locale, $faq['inLanguage']);
            self::assertCount(4, $faq['mainEntity']);

            $expectedQuestions = array_map(
                static fn (string $key): string => $translator->trans(
                    'public.home.faq.items.' . $key . '.question',
                    [],
                    $locale,
                ),
                $faqKeys,
            );
            self::assertSame($expectedQuestions, array_column($faq['mainEntity'], 'name'));
            foreach ($faq['mainEntity'] as $item) {
                self::assertSame('Question', $item['@type']);
                self::assertSame('Answer', $item['acceptedAnswer']['@type']);
                self::assertNotSame('', $item['acceptedAnswer']['text']);
            }
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

        self::assertCount(24, $locations);
        self::assertCount(24, $lastModified);
        self::assertCount(96, $alternates);
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
        self::assertContains('http://localhost/fr/fonctionnalites', $urls);
        self::assertContains('http://localhost/es/funciones', $urls);
        self::assertContains('http://localhost/en/contact', $urls);
        self::assertContains('http://localhost/fr/contact', $urls);
        self::assertContains('http://localhost/es/contacto', $urls);
        self::assertNotContains('http://localhost/fr/features', $urls);

        foreach ($lastModified as $date) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date->textContent);
        }
    }
}
