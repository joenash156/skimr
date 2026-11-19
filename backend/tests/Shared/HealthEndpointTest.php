<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Tests\AppTestCase;

final class HealthEndpointTest extends AppTestCase
{
    public function testHealthReturnsOkWithIsoTime(): void
    {
        $response = $this->request('GET', '/api/v1/health');

        self::assertSame(200, $response->getStatusCode());
        $body = self::json($response);
        self::assertIsArray($body['data']);
        self::assertSame('ok', $body['data']['status']);
        self::assertIsString($body['data']['time']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $body['data']['time']));
    }

    public function testUnknownApiRouteIsJson404WithRequestId(): void
    {
        $response = $this->request('GET', '/api/v1/does-not-exist');

        self::assertSame(404, $response->getStatusCode());
        $error = self::json($response)['error'];
        self::assertIsArray($error);
        self::assertSame('NOT_FOUND', $error['code']);
        self::assertSame($response->getHeaderLine('X-Request-Id'), $error['request_id']);
    }

    public function testWrongMethodIs405(): void
    {
        $response = $this->request('DELETE', '/api/v1/health');

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('GET', $response->getHeaderLine('Allow'));
    }
}
