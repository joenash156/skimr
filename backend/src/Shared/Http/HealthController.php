<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Clock\Clock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/v1/health: liveness check; touches no database or external service. */
final class HealthController
{
    public function __construct(
        private readonly JsonResponder $responder,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->responder->success($response, [
            'status' => 'ok',
            'time' => $this->clock->now()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
