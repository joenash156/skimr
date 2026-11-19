<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The only way controllers write JSON (spec 10.1):
 *   success: {"data": ..., "meta": {...}}
 *   error:   {"error": {"code", "message", "fields", "request_id"}}
 */
final class JsonResponder
{
    private const FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @param array<string, mixed> $meta
     */
    public function success(ResponseInterface $response, mixed $data, array $meta = [], int $status = 200): ResponseInterface
    {
        $body = ['data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return $this->write($response, $body, $status);
    }

    /**
     * A page of a list. per_page is capped at 100 (spec 10.1).
     *
     * @param list<mixed> $items
     */
    public function paginated(ResponseInterface $response, array $items, int $page, int $perPage, int $total): ResponseInterface
    {
        return $this->success($response, $items, [
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
            'total' => max(0, $total),
        ]);
    }

    public function created(ResponseInterface $response, mixed $data): ResponseInterface
    {
        return $this->success($response, $data, [], 201);
    }

    public function noContent(ResponseInterface $response): ResponseInterface
    {
        return $response->withStatus(204);
    }

    /**
     * @param array<string, string> $fields field name => translated message
     * @param array<string, mixed> $extra additional keys inside "error" (e.g. debug details)
     */
    public function error(
        ResponseInterface $response,
        int $status,
        string $code,
        string $message,
        array $fields = [],
        ?string $requestId = null,
        array $extra = [],
    ): ResponseInterface {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }
        if ($requestId !== null) {
            $error['request_id'] = $requestId;
        }

        return $this->write($response, ['error' => $error + $extra], $status);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function write(ResponseInterface $response, array $body, int $status): ResponseInterface
    {
        $response->getBody()->write(json_encode($body, self::FLAGS));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
