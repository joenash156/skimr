<?php

declare(strict_types=1);

namespace App\Shared\Coverage;

/**
 * Spec 20: at least 70% line coverage in src/Auth and src/Security.
 * Reads PHPUnit's Clover XML and sums statement coverage per folder.
 */
final class CoverageGate
{
    /**
     * @param array<string, float> $minimums folder (relative to backend/, e.g. "src/Auth") => minimum percent
     * @return array<string, array{covered: int, total: int, percent: float, minimum: float, ok: bool}>
     */
    public static function evaluate(string $cloverXml, array $minimums): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($cloverXml);
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new \RuntimeException('Coverage report is not valid XML.');
        }

        $totals = array_fill_keys(array_keys($minimums), ['covered' => 0, 'total' => 0]);
        foreach ($xml->xpath('//file') ?: [] as $file) {
            $path = str_replace('\\', '/', (string) $file['name']);
            $metrics = $file->metrics;
            if ($metrics === null) {
                continue;
            }
            foreach (array_keys($minimums) as $folder) {
                if (str_contains($path, '/' . trim($folder, '/') . '/')) {
                    $totals[$folder]['covered'] += (int) $metrics['coveredstatements'];
                    $totals[$folder]['total'] += (int) $metrics['statements'];
                }
            }
        }

        $result = [];
        foreach ($minimums as $folder => $minimum) {
            ['covered' => $covered, 'total' => $total] = $totals[$folder];
            $percent = $total === 0 ? 0.0 : round($covered / $total * 100, 1);
            $result[$folder] = [
                'covered' => $covered,
                'total' => $total,
                'percent' => $percent,
                'minimum' => $minimum,
                // An empty folder means the report is wrong (or the driver didn't run): fail.
                'ok' => $total > 0 && $percent >= $minimum,
            ];
        }

        return $result;
    }
}
