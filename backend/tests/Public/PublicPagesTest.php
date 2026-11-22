<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Tests\AppTestCase;

final class PublicPagesTest extends AppTestCase
{
    public function testEnglishPublicPagesHaveUniqueBoundedMetadataAndOneHeading(): void
    {
        $paths = [
            '/en/' => 'Skimr — evidence-led literature reviews',
            '/en/features' => 'Research features | Skimr',
            '/en/how-it-works' => 'How Skimr works | Evidence-led research',
            '/en/about' => 'About Skimr | Research, grounded in evidence',
            '/en/privacy' => 'Privacy policy | Skimr',
            '/en/terms' => 'Terms of use | Skimr',
            '/en/cookies' => 'Cookies | Skimr',
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

    public function testEnglishContentRoutesRemainUnavailableInUntranslatedLocales(): void
    {
        $response = $this->request('GET', '/fr/features');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('<html lang="fr">', (string) $response->getBody());
    }

    public function testExpandedEnglishHomepageDoesNotLeakIntoOtherLocales(): void
    {
        foreach (['fr', 'es'] as $locale) {
            $response = $this->request('GET', '/' . $locale . '/');
            $html = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode());
            self::assertStringNotContainsString('Bring your sources together', $html);
            self::assertStringNotContainsString('public.home.', $html);
        }
    }
}
