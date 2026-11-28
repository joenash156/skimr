<?php

declare(strict_types=1);

namespace App\Public\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Cache-busting URLs for files in public/:
 *   {{ asset('/assets/site.css') }}  ->  /assets/site.css?v=1764243200
 * The version is the file's modification time, so every deploy (or local build)
 * changes the URL and browsers fetch the new file instead of a stale copy. That
 * includes favicons, which browsers cache much longer than anything else.
 *
 *   {% if asset_exists('/app/assets/auth-island.js') %} ... {% endif %}
 * renders a tag only when the file was built.
 */
final class AssetExtension extends AbstractExtension
{
    /** @var array<string, int|null> */
    private array $mtimes = [];

    public function __construct(private readonly string $publicDir)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', $this->url(...)),
            new TwigFunction('asset_exists', fn (string $path): bool => $this->mtime($path) !== null),
        ];
    }

    public function url(string $path): string
    {
        $mtime = $this->mtime($path);

        return $mtime === null ? $path : $path . '?v=' . $mtime;
    }

    private function mtime(string $path): ?int
    {
        if (array_key_exists($path, $this->mtimes)) {
            return $this->mtimes[$path];
        }

        $mtime = null;
        // Only plain absolute paths inside public/: no "..", no query, no scheme.
        if (preg_match('~^/(?!.*\.\.)[A-Za-z0-9._/-]+$~', $path) === 1) {
            $file = $this->publicDir . $path;
            $time = is_file($file) ? filemtime($file) : false;
            $mtime = $time === false ? null : $time;
        }

        return $this->mtimes[$path] = $mtime;
    }
}
