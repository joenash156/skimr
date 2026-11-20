<?php

declare(strict_types=1);

namespace App\Shared\Contract;

/**
 * B3 task 1, automated: every API route the app registers must be described
 * in docs/api/openapi.yaml. CI runs bin/check-contract.php, so a PR that adds
 * an endpoint without updating the contract fails.
 *
 * Contract paths that have no route yet are fine (the contract comes first,
 * so P4 can build against the mock server); they're reported as "planned".
 *
 * The OpenAPI file is read line by line, relying on its layout: paths at two
 * spaces of indentation under `paths:`, HTTP methods at four. That's the
 * format the file already uses; the check fails loudly if it finds no paths.
 */
final class ContractChecker
{
    private const API_PREFIX = '/api/v1';
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * @return list<string> "METHOD /path" for every operation in the contract, relative to /api/v1
     */
    public static function contractOperations(string $openApiYaml): array
    {
        $operations = [];
        $inPaths = false;
        $currentPath = null;

        foreach (preg_split('/\R/', $openApiYaml) ?: [] as $line) {
            if (preg_match('/^(\S[^:]*):/', $line, $m)) {
                $inPaths = $m[1] === 'paths';
                $currentPath = null;
                continue;
            }
            if (!$inPaths) {
                continue;
            }
            if (preg_match('#^  (/\S*):\s*$#', $line, $m)) {
                $currentPath = $m[1];
                continue;
            }
            if ($currentPath !== null && preg_match('/^    (' . implode('|', self::METHODS) . '):\s*$/', $line, $m)) {
                $operations[] = strtoupper($m[1]) . ' ' . $currentPath;
            }
        }

        if ($operations === []) {
            throw new \RuntimeException('No operations found in the OpenAPI file: has its layout changed?');
        }
        sort($operations);

        return array_values(array_unique($operations));
    }

    /**
     * Turns Slim routes into the contract's form: /api/v1 removed, `{id:regex}`
     * placeholders reduced to `{id}`. Routes outside /api/v1 (the React shell,
     * P5's public pages) are skipped.
     *
     * @param iterable<array{methods: list<string>, pattern: string}> $routes
     * @return list<string> "METHOD /path"
     */
    public static function appOperations(iterable $routes): array
    {
        $operations = [];
        foreach ($routes as $route) {
            $pattern = $route['pattern'];
            if ($pattern !== self::API_PREFIX && !str_starts_with($pattern, self::API_PREFIX . '/')) {
                continue;
            }
            $path = self::stripPlaceholderRegexes(substr($pattern, strlen(self::API_PREFIX)));
            $path = $path === '' ? '/' : $path;

            foreach ($route['methods'] as $method) {
                $method = strtoupper($method);
                if (in_array(strtolower($method), self::METHODS, true)) {
                    $operations[] = $method . ' ' . $path;
                }
            }
        }
        sort($operations);

        return array_values(array_unique($operations));
    }

    /**
     * `{id:[0-9a-f-]{36}}` -> `{id}`. Counts braces, because the regex itself
     * may contain them (a plain `[^}]*` would stop at the first `}`).
     */
    private static function stripPlaceholderRegexes(string $path): string
    {
        $out = '';
        $length = strlen($path);
        for ($i = 0; $i < $length; $i++) {
            if ($path[$i] !== '{') {
                $out .= $path[$i];
                continue;
            }
            $depth = 1;
            $j = $i + 1;
            while ($j < $length && $depth > 0) {
                $depth += match ($path[$j]) {
                    '{' => 1,
                    '}' => -1,
                    default => 0,
                };
                $j++;
            }
            $inner = substr($path, $i + 1, $j - $i - 2);
            $out .= '{' . explode(':', $inner, 2)[0] . '}';
            $i = $j - 1;
        }

        return $out;
    }

    /**
     * @param list<string> $app
     * @param list<string> $contract
     * @return array{undocumented: list<string>, planned: list<string>}
     */
    public static function compare(array $app, array $contract): array
    {
        return [
            'undocumented' => array_values(array_diff($app, $contract)),
            'planned' => array_values(array_diff($contract, $app)),
        ];
    }
}
