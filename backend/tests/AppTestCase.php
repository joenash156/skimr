<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Base class for tests that send requests through the full application:
 * the real middleware chain, error handler and routes.
 */
abstract class AppTestCase extends TestCase
{
    /** @var App<ContainerInterface|null>|null */
    private ?App $app = null;

    /** @return App<ContainerInterface|null> */
    protected function app(): App
    {
        if ($this->app === null) {
            /** @var callable(): App<ContainerInterface|null> $create */
            $create = require dirname(__DIR__) . '/config/bootstrap.php';
            $this->app = $create();
        }

        return $this->app;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body sent as JSON when given
     */
    protected function request(string $method, string $path, array $headers = [], ?array $body = null, string $ip = '203.0.113.10'): ResponseInterface
    {
        return $this->app()->handle($this->buildRequest($method, $path, $headers, $body, $ip));
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     */
    protected function buildRequest(string $method, string $path, array $headers = [], ?array $body = null, string $ip = '203.0.113.10'): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $path, ['REMOTE_ADDR' => $ip]);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        return $request;
    }

    /** @return array<mixed> */
    protected static function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
