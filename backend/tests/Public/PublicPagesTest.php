<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Tests\AppTestCase;

final class PublicPagesTest extends AppTestCase
{
    public function testLocalizedPublicPagesHaveUniqueBoundedMetadataAndOneHeading(): void
    {
        $paths = [
            '/en/' => 'Skimr | evidence-led literature reviews',
            '/fr/' => 'Skimr | la recherche fondée sur des preuves',
            '/es/' => 'Skimr | investigación basada en evidencias',
            '/en/features' => 'Research features | Skimr',
            '/en/how-it-works' => 'How Skimr works | Evidence-led research',
            '/en/about' => 'About Skimr | Research, grounded in evidence',
            '/en/contact' => 'Contact Skimr | Questions and feedback',
            '/en/privacy' => 'Privacy policy | Skimr',
            '/en/terms' => 'Terms of use | Skimr',
            '/en/cookies' => 'Cookies | Skimr',
            '/fr/fonctionnalites' => 'Fonctionnalités de recherche | Skimr',
            '/fr/comment-ca-marche' => 'Comment fonctionne Skimr | Preuves vérifiables',
            '/fr/a-propos' => 'À propos de Skimr | Recherche et preuves',
            '/fr/contact' => 'Contacter Skimr | Questions et retours',
            '/fr/confidentialite' => 'Confidentialité | Skimr',
            '/fr/conditions-utilisation' => 'Conditions d’utilisation | Skimr',
            '/fr/cookies' => 'Préférences et cookies | Skimr',
            '/es/funciones' => 'Funciones de investigación | Skimr',
            '/es/como-funciona' => 'Cómo funciona Skimr | Evidencias verificables',
            '/es/acerca-de' => 'Acerca de Skimr | Investigación y evidencias',
            '/es/contacto' => 'Contacta con Skimr | Preguntas y comentarios',
            '/es/privacidad' => 'Privacidad | Skimr',
            '/es/terminos-de-uso' => 'Términos de uso | Skimr',
            '/es/cookies' => 'Preferencias y cookies | Skimr',
        ];
        $titles = [];
        $descriptions = [];

        foreach ($paths as $path => $expectedTitle) {
            $response = $this->request('GET', $path);
            $html = (string) $response->getBody();
            preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatches);
            preg_match('/<meta name="description" content="([^"]*)"/', $html, $descriptionMatches);
            preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $canonicalMatches);

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertArrayHasKey(1, $titleMatches, $path);
            self::assertArrayHasKey(1, $descriptionMatches, $path);
            self::assertArrayHasKey(1, $canonicalMatches, $path);

            $title = html_entity_decode($titleMatches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $description = html_entity_decode($descriptionMatches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            self::assertSame($expectedTitle, $title, $path);
            self::assertLessThanOrEqual(60, mb_strlen($title), $path . ' title is too long');
            self::assertLessThanOrEqual(155, mb_strlen($description), $path . ' description is too long');
            self::assertSame(1, substr_count($html, '<h1'), $path . ' must have exactly one h1');
            self::assertSame('http://localhost' . $path, html_entity_decode($canonicalMatches[1]), $path);
            self::assertStringNotContainsString('public.pages.', $html, $path . ' has an untranslated key');
            $titles[] = $title;
            $descriptions[] = $description;
        }

        self::assertCount(count(array_unique($titles)), $titles, 'Every page must have a unique title');
        self::assertCount(count(array_unique($descriptions)), $descriptions, 'Every page must have a unique description');
    }

    public function testFeaturesPageCoversEveryFeatureFromF07ThroughF23(): void
    {
        $response = $this->request('GET', '/en/features');
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        foreach (range(7, 23) as $featureId) {
            self::assertStringContainsString(sprintf('id="feature-f%02d"', $featureId), $html);
        }
        self::assertStringContainsString('Stretch goal', $html);
        self::assertStringContainsString('Feature tiers describe the product roadmap', $html);
    }

    public function testPrivacyAndTermsPagesIncludeRequiredSourceDisclosures(): void
    {
        $privacy = (string) $this->request('GET', '/en/privacy')->getBody();
        $terms = (string) $this->request('GET', '/en/terms')->getBody();

        self::assertStringContainsString('Google may use free-tier requests to improve its products', $privacy);
        self::assertStringContainsString('Do not upload confidential or unpublished work', $privacy);
        self::assertStringContainsString('location information', $privacy);
        self::assertStringContainsString('right to access and upload each source', $terms);
        self::assertStringContainsString('at most two sentences each', $terms);
        self::assertStringContainsString('does not display full source text, images, or snapshots', $terms);
    }

    public function testLocalizedPageRoutesResolveAndEnglishSlugsRemainUnavailable(): void
    {
        foreach (['/fr/fonctionnalites', '/es/funciones'] as $path) {
            self::assertSame(200, $this->request('GET', $path)->getStatusCode(), $path);
        }

        self::assertSame(404, $this->request('GET', '/fr/features')->getStatusCode());
        self::assertSame(404, $this->request('GET', '/es/features')->getStatusCode());
    }

    public function testPageLanguageSwitcherLinksToEquivalentLocalizedPages(): void
    {
        $pages = [
            '/en/features' => ['/en/features', '/fr/fonctionnalites', '/es/funciones'],
            '/fr/comment-ca-marche' => ['/en/how-it-works', '/fr/comment-ca-marche', '/es/como-funciona'],
            '/es/acerca-de' => ['/en/about', '/fr/a-propos', '/es/acerca-de'],
            '/en/contact' => ['/en/contact', '/fr/contact', '/es/contacto'],
            '/en/privacy' => ['/en/privacy', '/fr/confidentialite', '/es/privacidad'],
            '/fr/conditions-utilisation' => ['/en/terms', '/fr/conditions-utilisation', '/es/terminos-de-uso'],
            '/es/cookies' => ['/en/cookies', '/fr/cookies', '/es/cookies'],
        ];

        foreach ($pages as $source => $alternates) {
            $response = $this->request('GET', $source);
            $html = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode(), $source);
            foreach ($alternates as $alternate) {
                self::assertStringContainsString('href="http://localhost' . $alternate . '"', $html, $source);
            }
            self::assertStringNotContainsString('public.navigation.', $html, $source);
        }
    }

    public function testContactPageRendersLocalizedFormAndStatus(): void
    {
        $paths = ['/en/contact', '/fr/contact', '/es/contacto'];

        foreach ($paths as $path) {
            $response = $this->request('GET', $path);
            $html = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString('id="contact-form-status"', $html, $path);
            self::assertStringContainsString('name="name"', $html, $path);
            self::assertStringContainsString('name="email"', $html, $path);
            self::assertStringContainsString('name="message"', $html, $path);
            self::assertStringContainsString('data-contact-form', $html, $path);
            self::assertStringContainsString('data-contact-fields disabled', $html, $path);
        }
    }

    public function testExpandedHomepageRendersTranslatedContentInEveryLocale(): void
    {
        $homeHeadings = [
            'en' => 'Bring your sources together',
            'fr' => 'Rassemblez vos sources',
            'es' => 'Reúne tus fuentes',
        ];

        foreach ($homeHeadings as $locale => $heading) {
            $response = $this->request('GET', '/' . $locale . '/');
            $html = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString($heading, $html);
            self::assertSame(1, substr_count($html, '<h1'));
            self::assertStringNotContainsString('public.home.', $html);
        }
    }
}
