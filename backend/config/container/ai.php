<?php

declare(strict_types=1);

use App\Ai\AiServiceClient;
use App\Shared\Clock\Clock;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

return [
    AiServiceClient::class => static function (ContainerInterface $container): AiServiceClient {
        /** @var array{ai_service: array{url: string, secret: ?string}} $settings */
        $settings = $container->get('settings');

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            static fn (
                int $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?Throwable $exception = null,
            ): bool => $retries < 1 && $exception instanceof ConnectException,
        ));

        return new AiServiceClient(
            new Client([
                'base_uri' => rtrim($settings['ai_service']['url'], '/') . '/',
                'timeout' => 20,
                'handler' => $stack,
            ]),
            $container->get(Clock::class),
            $settings['ai_service']['secret'],
        );
    },
];
