<?php

declare(strict_types=1);

namespace App\Tests;

use App\Users\User;
use App\Users\UserRepository;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

/**
 * Base class for tests that use the test database (skimr_test). Every test
 * starts with empty P1 tables.
 */
abstract class DatabaseTestCase extends AppTestCase
{
    /** Tables emptied before each test. Children first is not needed: FK checks are off while truncating. */
    private const TABLES = ['audit_logs', 'login_events', 'rate_limits', 'user_tokens', 'settings', 'users'];

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->db();
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $db->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function db(): Connection
    {
        $db = $this->service(Connection::class);
        self::assertInstanceOf(Connection::class, $db);

        return $db;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    protected function service(string $id): object
    {
        $container = $this->app()->getContainer();
        self::assertNotNull($container);
        $service = $container->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    /**
     * Inserts a user and returns it. Defaults: an active, verified role=user account.
     *
     * @param array<string, mixed> $overrides column => value
     */
    protected function createUser(array $overrides = []): User
    {
        $row = $overrides + [
            'uuid' => Uuid::uuid4()->toString(),
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'email' => 'user' . bin2hex(random_bytes(4)) . '@example.com',
            'email_verified_at' => '2026-01-01 00:00:00.000',
            'password_hash' => null,
            'token_version' => 0,
        ];
        $this->db()->insert('users', $row);

        $user = $this->service(UserRepository::class)->findByUuid((string) $row['uuid']);
        self::assertNotNull($user);

        return $user;
    }

    /** @return list<array<string, mixed>> */
    protected function auditRows(string $action): array
    {
        return $this->db()->fetchAllAssociative('SELECT * FROM audit_logs WHERE action = ? ORDER BY id', [$action]);
    }
}
