<?php

declare(strict_types=1);

use App\Ai\AiServiceClient;
use App\Shared\Clock\Clock;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;

return [
    AiServiceClient::class => static function (ContainerInterface $container): AiServiceClient {
        /** @var array{ai_service: array{url: string, secret: ?string}} $settings */
        $settings = $container->get('settings');

        return new AiServiceClient(
            new Client(),
            rtrim($settings['ai_service']['url'], '/'),
            $settings['ai_service']['secret'],
            $container->get(Clock::class),
        );
    },
];
