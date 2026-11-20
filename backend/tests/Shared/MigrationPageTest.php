<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Shared\Db;
use App\Shared\Migrations\MigrationPageController;
use App\Shared\Migrations\MigrationRunner;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/** The browser migration page for staging (B4). */
final class MigrationPageTest extends DatabaseTestCase
{
    private const TOKEN = 'test-migration-token-0123456789abcdef0123456789';

    private ?string $token = self::TOKEN;

    protected function containerOverrides(): array
    {
        // Not static, and reads $this->token when the controller is first needed (the first
        // request), so a test can change the token after setUp() has built the app.
        return [
            MigrationPageController::class => fn (ContainerInterface $c): MigrationPageController => new MigrationPageController(
                $c->get(MigrationRunner::class),
                $c->get(AuditLogger::class),
                new NullLogger(),
                $this->token,
                0, // no slow-down in tests
            ),
        ];
    }

    public function testThePageDoesNotExistWithoutAToken(): void
    {
        $this->token = null;

        self::assertSame(404, $this->request('GET', '/_system/migrate')->getStatusCode());
        self::assertSame(404, $this->request('POST', '/_system/migrate', [], ['token' => '', 'action' => 'run'])->getStatusCode());
    }

    public function testAShortTokenDoesNotEnableIt(): void
    {
        $this->token = 'too-short';

        self::assertSame(404, $this->request('GET', '/_system/migrate')->getStatusCode());
    }

    public function testShowsAFormAndRefusesAWrongToken(): void
    {
        $form = $this->request('GET', '/_system/migrate');
        self::assertSame(200, $form->getStatusCode());
        self::assertStringContainsString('name="token"', (string) $form->getBody());
        self::assertSame('no-store', $form->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('noindex', $form->getHeaderLine('X-Robots-Tag'));

        $wrong = $this->request('POST', '/_system/migrate', [], ['token' => 'nope', 'action' => 'run']);
        self::assertSame(403, $wrong->getStatusCode());
        self::assertSame([], $this->auditRows(AuditEvent::SYSTEM_MIGRATIONS_RUN));
    }

    public function testStatusAndRunOnAnUpToDateDatabase(): void
    {
        $status = $this->request('POST', '/_system/migrate', [], ['token' => self::TOKEN, 'action' => 'status']);
        self::assertSame(200, $status->getStatusCode());
        self::assertStringContainsString('Nothing pending', (string) $status->getBody());

        $run = $this->request('POST', '/_system/migrate', [], ['token' => self::TOKEN, 'action' => 'run']);
        self::assertSame(200, $run->getStatusCode());
        self::assertStringContainsString('up to date', (string) $run->getBody());
        self::assertCount(1, $this->auditRows(AuditEvent::SYSTEM_MIGRATIONS_RUN));
    }

    public function testTheRunnerBuildsAFreshEmptyDatabase(): void
    {
        // The first staging deploy: an empty database, not even the migrations table.
        $settings = $this->service(Connection::class)->getParams();
        $server = self::serverConnection();
        $server->executeStatement('DROP DATABASE IF EXISTS skimr_test_fresh');
        $server->executeStatement('CREATE DATABASE skimr_test_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        try {
            $fresh = Db::connect([
                'host' => (string) ($settings['host'] ?? '127.0.0.1'),
                'port' => (int) ($settings['port'] ?? 3306),
                'name' => 'skimr_test_fresh',
                'user' => (string) ($settings['user'] ?? 'root'),
                'pass' => (string) ($settings['password'] ?? ''),
            ]);
            $runner = new MigrationRunner($fresh, dirname(__DIR__, 2) . '/migrations.php');

            self::assertSame([], $runner->executed());
            self::assertNotSame([], $runner->pending());

            $ran = $runner->migrate();

            self::assertSame($ran, $runner->executed());
            self::assertSame([], $runner->pending());
            self::assertSame([], $runner->migrate(), 'A second run does nothing.');
            $tables = $fresh->fetchFirstColumn('SHOW TABLES');
            foreach (['users', 'user_tokens', 'login_events', 'rate_limits', 'audit_logs', 'settings'] as $table) {
                self::assertContains($table, $tables);
            }
            $fresh->close();
        } finally {
            $server->executeStatement('DROP DATABASE IF EXISTS skimr_test_fresh');
        }
    }

    private static function serverConnection(): Connection
    {
        /** @var array{db: array{host: string, port: int, name: string, user: string, pass: string}} $settings */
        $settings = require dirname(__DIR__, 2) . '/config/settings.php';
        $params = Db::params($settings['db']);
        unset($params['dbname']);

        return DriverManager::getConnection($params);
    }
}
