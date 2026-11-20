<?php

declare(strict_types=1);

/*
 * Fails (exit 1) when the app has an /api/v1 route that docs/api/openapi.yaml
 * doesn't describe (P1 brief B3 task 1). Run by CI on every PR:
 *
 *     php bin/check-contract.php      (or: composer contract)
 *
 * Builds the app exactly as it runs, so every module's routes are included,
 * but makes no database or network calls.
 */

use App\Shared\Contract\ContractChecker;
use Psr\Container\ContainerInterface;
use Slim\App;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Route registration needs a valid-looking config, not real secrets: placeholders are fine here.
foreach ([
    'JWT_ACCESS_SECRET' => str_repeat('a', 64),
    'JWT_REFRESH_SECRET' => str_repeat('b', 64),
] as $name => $value) {
    if (getenv($name) === false && !isset($_ENV[$name])) {
        $_ENV[$name] = $value;
    }
}

/** @var callable(): App<ContainerInterface|null> $create */
$create = require dirname(__DIR__) . '/config/bootstrap.php';
$app = $create();

$routes = [];
foreach ($app->getRouteCollector()->getRoutes() as $route) {
    $routes[] = ['methods' => array_values($route->getMethods()), 'pattern' => $route->getPattern()];
}

$contractFile = dirname(__DIR__, 2) . '/docs/api/openapi.yaml';
$yaml = @file_get_contents($contractFile);
if ($yaml === false) {
    fwrite(STDERR, "Contract not found: {$contractFile}\n");
    exit(1);
}

$result = ContractChecker::compare(ContractChecker::appOperations($routes), ContractChecker::contractOperations($yaml));

if ($result['planned'] !== []) {
    echo "In the contract, not built yet (fine, contract-first):\n";
    foreach ($result['planned'] as $operation) {
        echo "  - {$operation}\n";
    }
}

if ($result['undocumented'] !== []) {
    fwrite(STDERR, "\nRoutes missing from docs/api/openapi.yaml (add them in the same PR):\n");
    foreach ($result['undocumented'] as $operation) {
        fwrite(STDERR, "  x {$operation}\n");
    }
    exit(1);
}

echo "\nContract check passed: every /api/v1 route is in docs/api/openapi.yaml.\n";
