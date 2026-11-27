<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\AiServiceClient;
use App\Shared\Clock\FrozenClock;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AiServiceClientTest extends TestCase
{
    public function testSignsTheExactJsonBodyWithTheTimestampHeader(): void
    {
        $mock = new MockHandler([
            new Response(202, ['Content-Type' => 'application/json'], '{"job_id":"remote-123","status":"queued"}'),
        ]);
        $stack = HandlerStack::create($mock);
        $history = [];
        $stack->push(static function (callable $handler) use (&$history): callable {
            return static function ($request, array $options) use ($handler, &$history) {
                $history[] = $request;
                return $handler($request, $options);
            };
        });

        $client = new AiServiceClient(
            new Client(['handler' => $stack]),
            'http://ai.test',
            'test-secret',
            new FrozenClock('2026-11-27 01:00:00'),
        );
        $result = $client->submitJob('extract_source', ['kind' => 'pdf', 'file_url' => 'https://example.test/file.pdf']);

        self::assertSame('remote-123', $result['job_id']);
        self::assertCount(1, $history);
        $request = $history[0];
        $body = (string) $request->getBody();
        $timestamp = $request->getHeaderLine('X-Timestamp');
        self::assertSame(
            hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret'),
            $request->getHeaderLine('X-Signature'),
        );
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertStringContainsString('"kind":"pdf"', $body);
    }

    public function testHealthReturnsDecodedPayload(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok","ready":true}'),
        ]);
        $client = new AiServiceClient(
            new Client(['handler' => HandlerStack::create($mock)]),
            'http://ai.test',
            'test-secret',
            new FrozenClock('2026-11-27 01:00:00'),
        );

        self::assertSame(['status' => 'ok', 'ready' => true], $client->health());
    }
}
