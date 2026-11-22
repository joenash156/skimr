<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Tests\AppTestCase;

final class PublicRoutesTest extends AppTestCase
{
    public function testRootRedirectsToEnglishHome(): void
    {
        $response = $this->request('GET', '/');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/en/', $response->getHeaderLine('Location'));
    }

    public function testLanguageHomeWithoutSlashRedirectsToCanonicalHome(): void
    {
        $response = $this->request('GET', '/fr');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/fr/', $response->getHeaderLine('Location'));
    }

    public function testNonHomeTrailingSlashRedirectsWithoutQueryStringLoss(): void
    {
        $response = $this->request('GET', '/en/missing/?source=test');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/en/missing?source=test', $response->getHeaderLine('Location'));
    }

    public function testHomeRendersLocaleAndNonceBoundThemeScript(): void
    {
        $response = $this->request('GET', '/fr/');
        $html = (string) $response->getBody();
        preg_match('/<script nonce="([^"]+)">/', $html, $matches);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<html lang="fr">', $html);
        self::assertStringContainsString('Aller au contenu', $html);
        self::assertArrayHasKey(1, $matches);
        self::assertStringContainsString(
            "'nonce-" . $matches[1] . "'",
            $response->getHeaderLine('Content-Security-Policy'),
        );
    }

    public function testUnknownPublicPageIsLocalizedHtml404(): void
    {
        $response = $this->request('GET', '/es/no-existe');
        $html = (string) $response->getBody();

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<html lang="es">', $html);
        self::assertStringContainsString('Página no encontrada', $html);
        self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
    }

    public function testUnsupportedLocaleIsAnHtml404(): void
    {
        $response = $this->request('GET', '/de/');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
    }

    public function testNonPublicPathFallsBackToTheDefaultErrorResponse(): void
    {
        $response = $this->request('GET', '/not-a-public-page');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }
}
