<?php

declare(strict_types=1);

/*
 * PHPUnit bootstrap: makes sure the test database exists and is fully
 * migrated before any test runs. phpunit.xml.dist forces DB_NAME=skimr_test,
 * so your dev database is never touched. Host, user and password come from
 * the repo-root .env (locally) or the CI environment.
 */

use App\Shared\Db;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable([dirname(__DIR__), dirname(__DIR__, 2)])->safeLoad();

/** @var array{db: array{host: string, port: int, name: string, user: string, pass: string}} $settings */
$settings = require dirname(__DIR__) . '/config/settings.php';
$db = $settings['db'];

if (!preg_match('/^[A-Za-z0-9_]+$/', $db['name']) || !str_contains($db['name'], 'test')) {
    fwrite(STDERR, "Refusing to run tests: DB_NAME must be a test database (got '{$db['name']}').\n");
    exit(1);
}

try {
    $serverParams = Db::params($db);
    unset($serverParams['dbname']); // connect to the server only; the database may not exist yet
    $server = DriverManager::getConnection($serverParams);
    $server->executeStatement(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $db['name'],
    ));
    $server->close();

    $connection = Db::connect($db);
    $dependencies = DependencyFactory::fromConnection(
        new PhpFile(dirname(__DIR__) . '/migrations.php'),
        new ExistingConnection($connection),
    );
    $dependencies->getMetadataStorage()->ensureInitialized();
    $latest = $dependencies->getVersionAliasResolver()->resolveVersionAlias('latest');
    $plan = $dependencies->getMigrationPlanCalculator()->getPlanUntilVersion($latest);
    if (count($plan) > 0) {
        $dependencies->getMigrator()->migrate($plan, new MigratorConfiguration());
    }
    $connection->close();
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf(
        "Could not prepare the test database '%s' on %s:%d: %s\nIs MySQL running, and are DB_HOST/DB_USER/DB_PASS set in the repo-root .env?\n",
        $db['name'],
        $db['host'],
        $db['port'],
        $e->getMessage(),
    ));
    exit(1);
}
