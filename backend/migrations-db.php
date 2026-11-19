<?php

declare(strict_types=1);

use App\Shared\Db;
use Dotenv\Dotenv;

/*
 * Database connection for the Doctrine Migrations CLI. Uses the same
 * settings and connection parameters as the application.
 */

require_once __DIR__ . '/vendor/autoload.php';

Dotenv::createImmutable([__DIR__, dirname(__DIR__)])->safeLoad();

/** @var array{db: array{host: string, port: int, name: string, user: string, pass: string}} $settings */
$settings = require __DIR__ . '/config/settings.php';

return Db::params($settings['db']);
