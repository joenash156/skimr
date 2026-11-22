<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Shared\Translator;
use PHPUnit\Framework\TestCase;

final class PublicTranslationsTest extends TestCase
{
    private const ENGLISH_ONLY_PREFIXES = [
        'public.footer_navigation',
        'public.navigation.features',
        'public.navigation.how_it_works',
        'public.navigation.about',
        'public.navigation.privacy',
        'public.navigation.terms',
        'public.navigation.cookies',
        'public.home.eyebrow',
        'public.home.intro',
        'public.home.primary_cta',
        'public.home.secondary_cta',
        'public.home.how_it_works.',
        'public.home.sources.',
        'public.home.highlights.',
        'public.home.evidence.',
        'public.home.faq.',
        'public.home.cta.',
        'public.pages.',
    ];

    public function testSharedPublicTranslationKeysExistInFrenchAndSpanish(): void
    {
        $directory = dirname(__DIR__, 2) . '/translations/public';
        $catalogues = [];

        foreach (Translator::SUPPORTED as $locale) {
            $path = $directory . '/' . $locale . '.json';
            $decoded = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            $catalogues[$locale] = self::flatten($decoded);
        }

        foreach (array_keys($catalogues['en']) as $key) {
            if (self::isEnglishOnly($key)) {
                continue;
            }

            self::assertArrayHasKey($key, $catalogues['fr'], 'Missing French translation: ' . $key);
            self::assertArrayHasKey($key, $catalogues['es'], 'Missing Spanish translation: ' . $key);
        }
    }

    private static function isEnglishOnly(string $key): bool
    {
        foreach (self::ENGLISH_ONLY_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $tree
     * @return array<string, string>
     */
    private static function flatten(array $tree, string $prefix = ''): array
    {
        $flat = [];
        foreach ($tree as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += self::flatten($value, $fullKey);
            } elseif (is_scalar($value)) {
                $flat[$fullKey] = (string) $value;
            }
        }

        return $flat;
    }
}
