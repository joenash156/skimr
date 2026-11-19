<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
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
     */
    protected function request(string $method, string $path, array $headers = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $path);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->app()->handle($request);
    }

    /** @return array<mixed> */
    protected static function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
