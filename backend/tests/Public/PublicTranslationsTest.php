<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Shared\Translator;
use PHPUnit\Framework\TestCase;

final class PublicTranslationsTest extends TestCase
{
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
            self::assertArrayHasKey($key, $catalogues['fr'], 'Missing French translation: ' . $key);
            self::assertArrayHasKey($key, $catalogues['es'], 'Missing Spanish translation: ' . $key);
        }
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
