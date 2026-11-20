<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Contract\ContractChecker;
use App\Tests\AppTestCase;

final class ContractCheckerTest extends AppTestCase
{
    private const YAML = <<<'YAML'
        openapi: 3.1.0
        paths:
          /projects:
            get:
              summary: List
            post:
              summary: Create
          /projects/{id}:
            parameters:
              - name: id
            delete:
              summary: Delete
        components:
          schemas:
            /not-a-path:
              get: {}
        YAML;

    public function testReadsOperationsFromThePathsSectionOnly(): void
    {
        self::assertSame(
            ['DELETE /projects/{id}', 'GET /projects', 'POST /projects'],
            ContractChecker::contractOperations(self::YAML),
        );
    }

    public function testFlagsRoutesMissingFromTheContractAndListsPlannedOnes(): void
    {
        $app = ContractChecker::appOperations([
            ['methods' => ['GET'], 'pattern' => '/api/v1/projects'],
            ['methods' => ['DELETE'], 'pattern' => '/api/v1/projects/{id:[0-9a-f-]{36}}'],
            ['methods' => ['PATCH'], 'pattern' => '/api/v1/projects/{id}'],   // forgot the contract
            ['methods' => ['GET'], 'pattern' => '/app[/{path:.*}]'],           // not API: ignored
            ['methods' => ['GET'], 'pattern' => '/'],                           // P5 public page: ignored
        ]);

        $result = ContractChecker::compare($app, ContractChecker::contractOperations(self::YAML));

        self::assertSame(['PATCH /projects/{id}'], $result['undocumented']);
        self::assertSame(['POST /projects'], $result['planned']);
    }

    public function testFailsLoudlyWhenTheLayoutCantBeRead(): void
    {
        $this->expectException(\RuntimeException::class);
        ContractChecker::contractOperations("openapi: 3.1.0\ninfo: {}\n");
    }

    public function testTheRealAppAndContractAgree(): void
    {
        $routes = [];
        foreach ($this->app()->getRouteCollector()->getRoutes() as $route) {
            $routes[] = ['methods' => array_values($route->getMethods()), 'pattern' => $route->getPattern()];
        }
        $contract = ContractChecker::contractOperations((string) file_get_contents(dirname(__DIR__, 3) . '/docs/api/openapi.yaml'));

        self::assertSame([], ContractChecker::compare(ContractChecker::appOperations($routes), $contract)['undocumented']);
        self::assertContains('POST /auth/login', $contract);
        self::assertContains('DELETE /me', $contract);
        self::assertGreaterThanOrEqual(18, count($contract));
    }
}
