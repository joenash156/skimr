<?php

declare(strict_types=1);

namespace App\Ai;

use App\Shared\Clock\Clock;
use GuzzleHttp\ClientInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

final class AiServiceClient
{
    private const EMBEDDING_DIMENSIONS = 384;
    private const MAX_EMBED_TEXTS = 32;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly Clock $clock,
        private readonly ?string $secret,
    ) {
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->requestJson('GET', 'health');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function submitJob(string $type, array $payload): string
    {
        $type = trim($type);
        if ($type === '') {
            throw new \InvalidArgumentException('AI job type must not be empty.');
        }

        $response = $this->requestJson('POST', 'v1/jobs', [
            'type' => $type,
            'payload' => $payload,
            'callback' => null,
        ], 202);
        $jobId = $response['job_id'] ?? null;
        if (!is_string($jobId) || trim($jobId) === '') {
            throw new UnexpectedValueException('AI service job response is missing a valid job_id.');
        }

        return $jobId;
    }

    /**
     * @return array{
     *     status: 'queued'|'running'|'succeeded'|'failed',
     *     progress: int,
     *     result?: array<string, mixed>|null,
     *     error?: array{code: string, message: string}|null,
     *     usage: list<array<string, mixed>>
     * }
     */
    public function getJob(string $remoteId): array
    {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            throw new \InvalidArgumentException('AI job id must not be empty.');
        }

        $path = 'v1/jobs/' . rawurlencode($remoteId);
        $response = $this->requestJson('GET', $path);
        $status = $response['status'] ?? null;
        $progress = $response['progress'] ?? null;
        $usage = $response['usage'] ?? null;
        if (!in_array($status, ['queued', 'running', 'succeeded', 'failed'], true)) {
            throw new UnexpectedValueException('AI service job response has an invalid status.');
        }
        if (!is_int($progress) || $progress < 0 || $progress > 100) {
            throw new UnexpectedValueException('AI service job response has invalid progress.');
        }
        if (!is_array($usage) || !array_is_list($usage)) {
            throw new UnexpectedValueException('AI service job response has invalid usage data.');
        }
        foreach ($usage as $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new UnexpectedValueException('AI service job response has invalid usage data.');
            }
        }

        $result = $response['result'] ?? null;
        if ($result !== null && (!is_array($result) || array_is_list($result))) {
            throw new UnexpectedValueException('AI service job response has an invalid result.');
        }
        $error = $response['error'] ?? null;
        if ($error !== null && (
            !is_array($error)
            || !is_string($error['code'] ?? null)
            || !is_string($error['message'] ?? null)
        )) {
            throw new UnexpectedValueException('AI service job response has an invalid error.');
        }

        /** @var array{
         *     status: 'queued'|'running'|'succeeded'|'failed',
         *     progress: int,
         *     result?: array<string, mixed>|null,
         *     error?: array{code: string, message: string}|null,
         *     usage: list<array<string, mixed>>
         * } $response
         */
        return $response;
    }

    /**
     * @param array<mixed> $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        if (!array_is_list($texts) || count($texts) > self::MAX_EMBED_TEXTS) {
            throw new \InvalidArgumentException('Embedding requests must contain at most 32 texts in a list.');
        }
        foreach ($texts as $text) {
            if (!is_string($text)) {
                throw new \InvalidArgumentException('Every embedding input must be a string.');
            }
        }

        $response = $this->requestJson('POST', 'v1/embed', ['texts' => $texts]);
        $vectors = $response['vectors'] ?? null;
        if (!is_array($vectors) || !array_is_list($vectors) || count($vectors) !== count($texts)) {
            throw new UnexpectedValueException('AI service embedding response has an invalid vectors list.');
        }

        $normalized = [];
        foreach ($vectors as $vector) {
            if (!is_array($vector) || !array_is_list($vector) || count($vector) !== self::EMBEDDING_DIMENSIONS) {
                throw new UnexpectedValueException('AI service returned an embedding with an invalid dimension.');
            }

            $normalizedVector = [];
            foreach ($vector as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new UnexpectedValueException('AI service returned an embedding with a non-numeric value.');
                }
                $float = (float) $value;
                if (!is_finite($float)) {
                    throw new UnexpectedValueException('AI service returned an embedding with a non-finite value.');
                }
                $normalizedVector[] = $float;
            }
            $normalized[] = $normalizedVector;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, ?array $body = null, int $expectedStatus = 200): array
    {
        if (!is_string($this->secret) || trim($this->secret) === '') {
            throw new RuntimeException('AI_SERVICE_SECRET is not configured.');
        }

        try {
            $rawBody = $body === null
                ? ''
                : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('AI service request could not be encoded as JSON.', 0, $exception);
        }

        $timestamp = (string) $this->clock->now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->secret);
        $headers = [
            'Accept' => 'application/json',
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->http->request($method, $path, [
            'headers' => $headers,
            'body' => $rawBody,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);

        return $this->decodeResponse($response, $method, $path, $expectedStatus);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(ResponseInterface $response, string $method, string $path, int $expectedStatus): array
    {
        $status = $response->getStatusCode();
        $rawResponse = (string) $response->getBody();
        if ($status !== $expectedStatus) {
            throw new AiServiceException($status, $method, $path, self::errorCode($rawResponse));
        }

        try {
            $decoded = json_decode($rawResponse, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('AI service returned invalid JSON.', 0, $exception);
        }
        // Decoding to objects first tells `{}` (an object) apart from `[]` (a list).
        if (!$decoded instanceof \stdClass) {
            throw new UnexpectedValueException('AI service response must be a JSON object.');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($rawResponse, true, 64, JSON_THROW_ON_ERROR);

        return $data;
    }

    /** The service's `{"error": {"code": "..."}}`, if the body has a well-formed one. */
    private static function errorCode(string $rawResponse): ?string
    {
        try {
            $body = json_decode($rawResponse, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        $code = is_array($body) && is_array($body['error'] ?? null) ? ($body['error']['code'] ?? null) : null;

        return is_string($code) && preg_match('/^[a-z][a-z_]{0,39}$/', $code) === 1 ? $code : null;
    }
}
