<?php

declare(strict_types=1);

namespace App\Shared;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Builds the single DBAL connection (spec 8, 9). Repositories receive the
 * Connection by injection and use only prepared statements or the query
 * builder (spec 11.2). The connection is lazy: nothing touches MySQL until
 * the first query, so routes that need no database (health) never fail on it.
 */
final class Db
{
    /**
     * @param array{host: string, port: int, name: string, user: string, pass: string} $config
     */
    public static function connect(array $config): Connection
    {
        return DriverManager::getConnection(self::params($config));
    }

    /**
     * Connection parameters, shared with migrations-db.php so the app and
     * migrations always connect the same way.
     *
     * @param array{host: string, port: int, name: string, user: string, pass: string} $config
     * @return array{driver: 'pdo_mysql', host: string, port: int, dbname: string, user: string, password: string, charset: string, driverOptions: array<int, mixed>}
     */
    public static function params(array $config): array
    {
        return [
            'driver' => 'pdo_mysql',
            'host' => $config['host'],
            'port' => $config['port'],
            'dbname' => $config['name'],
            'user' => $config['user'],
            'password' => $config['pass'],
            'charset' => 'utf8mb4',
            'driverOptions' => [
                // Store and compare every DATETIME in UTC (spec 9 conventions).
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00', NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ],
        ];
    }
}
