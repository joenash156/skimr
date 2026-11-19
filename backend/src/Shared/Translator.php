<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Reads JSON catalogues (spec 13) and returns translated strings.
 *
 * A catalogue directory holds either `{locale}.json` or one file per module,
 * `{module}.{locale}.json` (e.g. `auth.fr.json`, `sources.fr.json`). Every
 * file for a locale is merged, so module owners never edit the same file.
 * Nested JSON objects are flattened to dotted keys: {"auth": {"x": "y"}}
 * becomes "auth.x". Placeholders look like {name}.
 *
 * Fallback order for a key: requested locale, then English, then the key itself.
 */
final class Translator
{
    public const SUPPORTED = ['en', 'fr', 'es'];
    public const DEFAULT = 'en';

    /** @var array<string, array<string, string>> locale => (key => text) */
    private array $catalogues = [];

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public function trans(string $key, array $params = [], ?string $locale = null): string
    {
        $locale = self::isSupported($locale) ? $locale : self::DEFAULT;

        $text = $this->catalogue($locale)[$key]
            ?? $this->catalogue(self::DEFAULT)[$key]
            ?? $key;

        if ($params === []) {
            return $text;
        }

        $replacements = [];
        foreach ($params as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $replacements);
    }

    public function has(string $key, string $locale): bool
    {
        return isset($this->catalogue($locale)[$key]);
    }

    /** @phpstan-assert-if-true 'en'|'fr'|'es' $locale */
    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::SUPPORTED, true);
    }

    /**
     * Picks the best supported locale from an Accept-Language header,
     * e.g. "fr-CA,fr;q=0.9,en;q=0.8" gives "fr". Returns null if none match.
     */
    public static function negotiate(?string $acceptLanguage): ?string
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $acceptLanguage) as $position => $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(substr(trim($pieces[0]), 0, 2));
            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $parameter) {
                $parameter = trim($parameter);
                if (str_starts_with($parameter, 'q=')) {
                    $quality = (float) substr($parameter, 2);
                }
            }
            if ($quality > 0 && self::isSupported($tag)) {
                // Earlier entries win ties, as browsers list preferences in order.
                $candidates[] = ['tag' => $tag, 'q' => $quality, 'pos' => $position];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => [$b['q'], $a['pos']] <=> [$a['q'], $b['pos']]);

        return $candidates[0]['tag'];
    }

    /** @return array<string, string> */
    private function catalogue(string $locale): array
    {
        if (isset($this->catalogues[$locale])) {
            return $this->catalogues[$locale];
        }

        $files = array_merge(
            glob($this->directory . '/' . $locale . '.json') ?: [],
            glob($this->directory . '/*.' . $locale . '.json') ?: [],
        );
        sort($files);

        $merged = [];
        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $merged = array_merge($merged, self::flatten($decoded));
            }
        }

        return $this->catalogues[$locale] = $merged;
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
