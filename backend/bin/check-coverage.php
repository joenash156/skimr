<?php

declare(strict_types=1);

/*
 * Fails (exit 1) when line coverage in src/Auth or src/Security is below
 * 70% (spec 20). CI runs it after the tests:
 *
 *     composer test -- --coverage-clover var/coverage.xml
 *     php bin/check-coverage.php var/coverage.xml
 *
 * Measuring coverage needs a driver (PCOV or Xdebug). CI installs PCOV;
 * you don't need one locally.
 */

use App\Shared\Coverage\CoverageGate;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$report = $argv[1] ?? dirname(__DIR__) . '/var/coverage.xml';
$xml = @file_get_contents($report);
if ($xml === false) {
    fwrite(STDERR, "Coverage report not found: {$report}\n");
    exit(1);
}

$results = CoverageGate::evaluate($xml, ['src/Auth' => 70.0, 'src/Security' => 70.0]);

$failed = false;
foreach ($results as $folder => $r) {
    printf(
        "%s %-14s %5.1f%% (%d/%d lines; minimum %.0f%%)\n",
        $r['ok'] ? 'ok  ' : 'FAIL',
        $folder,
        $r['percent'],
        $r['covered'],
        $r['total'],
        $r['minimum'],
    );
    $failed = $failed || !$r['ok'];
}

exit($failed ? 1 : 0);
