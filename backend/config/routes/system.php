<?php

declare(strict_types=1);

use App\Shared\Http\HealthController;
use App\Shared\Http\SpaController;
use App\Shared\Migrations\MigrationPageController;
use Slim\App;

/*
 * System routes (P1): health check and the React dashboard shell.
 * Each module has its own file in this folder; see config/bootstrap.php.
 *
 * @param App<\Psr\Container\ContainerInterface|null> $app
 */
return static function (App $app): void {
    $app->get('/api/v1/health', HealthController::class);

    $app->get('/app[/{path:.*}]', SpaController::class);

    // Browser migration page for staging (deploy/README.md). 404 unless MIGRATION_TOKEN is set.
    $app->get('/_system/migrate', [MigrationPageController::class, 'show']);
    $app->post('/_system/migrate', [MigrationPageController::class, 'act']);
};
