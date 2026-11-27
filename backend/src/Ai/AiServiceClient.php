<?php

declare(strict_types=1);

namespace App\Ai;

use App\Shared\Clock\Clock;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

final class AiServiceClient
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
        private readonly ?string $secret,
        private readonly Clock $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        $response = $this->request('GET', '/health', null);

        return $this->decode($response);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function submitJob(string $type, array $payload): array
    {
        $body = json_encode(['type' => $type, 'payload' => $payload, 'callback' => 'none'], JSON_THROW_ON_ERROR);

        return $this->decode($this->request('POST', '/v1/jobs', $body));
    }

    /** @return array<string, mixed> */
    public function getJob(string $remoteId): array
    {
        return $this->decode($this->request('GET', '/v1/jobs/' . rawurlencode($remoteId), null));
    }

    /** @param list<string> $texts
     *  @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        $body = json_encode(['texts' => $texts], JSON_THROW_ON_ERROR);
        $decoded = $this->decode($this->request('POST', '/v1/embed', $body));
        $vectors = $decoded['vectors'] ?? null;
        if (!is_array($vectors)) {
            throw new RuntimeException('AI service returned no embedding vectors.');
        }

        /** @var list<list<float>> $vectors */
        return $vectors;
    }

    private function request(string $method, string $path, ?string $body): \Psr\Http\Message\ResponseInterface
    {
        $body ??= '';
        $timestamp = (string) $this->clock->now()->getTimestamp();
        $secret = $this->secret;
        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException('AI_SERVICE_SECRET is not configured.');
        }
        $headers = [
            'Accept' => 'application/json',
            'X-Timestamp' => $timestamp,
            'X-Signature' => hash_hmac('sha256', $timestamp . '.' . $body, $secret),
        ];
        if ($body !== '') {
            $headers['Content-Type'] = 'application/json';
        }

        $options = [
            'headers' => $headers,
            'timeout' => 20,
        ];
        if ($body !== '') {
            $options['body'] = $body;
        }

        try {
            return $this->http->request($method, $this->baseUrl . $path, $options);
        } catch (ConnectException) {
            return $this->http->request($method, $this->baseUrl . $path, $options);
        }
    }

    /** @return array<string, mixed> */
    private function decode(\Psr\Http\Message\ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('AI service returned invalid JSON.');
        }

        return $decoded;
    }
}
