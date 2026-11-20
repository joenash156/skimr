<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\App;
use Slim\Factory\AppFactory;

/*
 * Builds the whole application. Used by public/index.php, bin/console and
 * the tests, so all three run exactly the same wiring.
 *
 * .env lookup: backend/.env first (staging uploads only backend/), then the
 * repository-root .env (local development). Variables already set in the
 * real environment (CI, PHPUnit) always win.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

/*
 * @param array<string, mixed> $overrides container definitions applied last (tests only)
 */
return static function (array $overrides = []): App {
    Dotenv::createImmutable([dirname(__DIR__), dirname(__DIR__, 2)])->safeLoad();

    /** @var array<string, mixed> $settings */
    $settings = require __DIR__ . '/settings.php';

    /** @var callable(array<string, mixed>, array<string, mixed>): \Psr\Container\ContainerInterface $buildContainer */
    $buildContainer = require __DIR__ . '/container.php';
    $app = AppFactory::createFromContainer($buildContainer($settings, $overrides));

    (require __DIR__ . '/middleware.php')($app);

    // Every module registers its own routes in config/routes/{module}.php (spec 18).
    $routeFiles = glob(__DIR__ . '/routes/*.php') ?: [];
    sort($routeFiles);
    foreach ($routeFiles as $file) {
        $register = require $file;
        if (!is_callable($register)) {
            throw new RuntimeException(sprintf('%s must return a callable that takes the Slim App.', $file));
        }
        $register($app);
    }

    return $app;
};
