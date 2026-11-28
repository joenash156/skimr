<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\AiServiceClient;
use App\Ai\AiServiceException;
use App\Shared\Clock\FrozenClock;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class AiServiceClientTest extends TestCase
{
    private const SECRET = 'local-test-secret';
    private const NOW = '2026-11-25 08:00:00';

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    public function testHealthSignsTheEmptyBodyAndReturnsTheJsonObject(): void
    {
        $client = $this->client([self::jsonResponse(200, ['status' => 'ok'])]);

        self::assertSame(['status' => 'ok'], $client->health());
        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('/health', $this->history[0]['request']->getUri()->getPath());
        self::assertSame('', (string) $this->history[0]['request']->getBody());
        $this->assertSignature($this->history[0]['request'], '');
    }

    public function testSubmitJobSendsTheAgreedEnvelopeAndReturnsItsRemoteId(): void
    {
        $client = $this->client([self::jsonResponse(202, ['job_id' => 'job-123'])]);
        $payload = ['source_uuid' => 'source-uuid', 'input' => ['kind' => 'text', 'text' => 'A / B']];

        self::assertSame('job-123', $client->submitJob('extract_source', $payload));
        $request = $this->history[0]['request'];
        $this->assertSignature($request, (string) $request->getBody());
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/jobs', $request->getUri()->getPath());
        self::assertSame(
            ['type' => 'extract_source', 'payload' => $payload, 'callback' => null],
            json_decode((string) $request->getBody(), true, 16, JSON_THROW_ON_ERROR),
        );
    }

    public function testGetJobEncodesTheIdAndValidatesTheResponse(): void
    {
        $job = [
            'status' => 'succeeded',
            'progress' => 100,
            'result' => ['status' => 'ready'],
            'usage' => [['provider' => 'local', 'tokens_in' => 0]],
        ];
        $client = $this->client([self::jsonResponse(200, $job)]);

        self::assertSame($job, $client->getJob('job/id with spaces'));
        $request = $this->history[0]['request'];
        self::assertSame('/v1/jobs/job%2Fid%20with%20spaces', $request->getUri()->getPath());
        $this->assertSignature($request, '');
    }

    public function testEmbedReturnsExactlyOneFinite384DimensionalVectorPerText(): void
    {
        $vectors = [
            array_fill(0, 384, 0.0),
            array_fill(0, 384, 0.25),
        ];
        $client = $this->client([self::jsonResponse(200, ['vectors' => $vectors])]);

        self::assertSame($vectors, $client->embed(['first text', 'second text']));
        $request = $this->history[0]['request'];
        self::assertSame(['texts' => ['first text', 'second text']], json_decode((string) $request->getBody(), true));
        $this->assertSignature($request, (string) $request->getBody());
    }

    public function testRejectsInvalidInputsBeforeSendingRequests(): void
    {
        $client = $this->client([]);

        try {
            $client->submitJob(' ', []);
            self::fail('Empty job types must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertCount(0, $this->history);
        }

        try {
            $client->getJob(' ');
            self::fail('Empty remote job ids must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertCount(0, $this->history);
        }

        try {
            $client->embed(['valid text', 42]);
            self::fail('Embedding inputs must all be strings.');
        } catch (\InvalidArgumentException) {
            self::assertCount(0, $this->history);
        }

        try {
            $client->embed(array_fill(0, 33, 'text'));
            self::fail('Embedding batches larger than 32 must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertCount(0, $this->history);
        }
    }

    public function testRetriesOneConnectionFailureButDoesNotRetryServerErrors(): void
    {
        $down = new ConnectException('connection failed', new Request('POST', 'http://ai.test/v1/jobs'));
        $client = $this->client([$down, self::jsonResponse(202, ['job_id' => 'after-retry'])], retry: true);

        self::assertSame('after-retry', $client->submitJob('extract_source', []));
        self::assertCount(2, $this->history);
        self::assertSame(
            (string) $this->history[0]['request']->getBody(),
            (string) $this->history[1]['request']->getBody(),
        );
        $this->assertSignature($this->history[0]['request'], (string) $this->history[0]['request']->getBody());
        $this->assertSignature($this->history[1]['request'], (string) $this->history[1]['request']->getBody());

        $serverError = $this->client([new Response(503, [], 'private upstream response')], retry: true);
        try {
            $serverError->health();
            self::fail('Non-success HTTP statuses must be surfaced.');
        } catch (AiServiceException $exception) {
            self::assertSame(503, $exception->httpStatus);
            self::assertStringNotContainsString('private upstream response', $exception->getMessage());
            self::assertCount(1, $this->history);
        }
    }

    public function testServiceErrorCodesAreKeptButNeverTheMessageOrUnsafeCodes(): void
    {
        $client = $this->client([
            self::jsonResponse(422, ['error' => ['code' => 'unknown_job_type', 'message' => 'Unknown job type: x']]),
            self::jsonResponse(401, ['error' => ['code' => 'Bad Code <script>', 'message' => 'secret detail']]),
        ]);

        try {
            $client->submitJob('x', []);
            self::fail('A 422 must be surfaced.');
        } catch (AiServiceException $exception) {
            self::assertSame(422, $exception->httpStatus);
            self::assertSame('unknown_job_type', $exception->errorCode);
            self::assertStringNotContainsString('Unknown job type', $exception->getMessage());
        }

        try {
            $client->health();
            self::fail('A 401 must be surfaced.');
        } catch (AiServiceException $exception) {
            self::assertNull($exception->errorCode);
            self::assertStringNotContainsString('secret detail', $exception->getMessage());
        }
    }

    public function testClientFailsExplicitlyWhenTheSharedSecretIsMissing(): void
    {
        $client = $this->client([self::jsonResponse(200, ['status' => 'ok'])], secret: null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI_SERVICE_SECRET is not configured.');
        $client->health();
    }

    /**
     * @param list<ResponseInterface|Throwable> $responses
     */
    private function client(array $responses, bool $retry = false, ?string $secret = self::SECRET): AiServiceClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        if ($retry) {
            $stack->push(Middleware::retry(
                static fn (
                    int $retries,
                    RequestInterface $request,
                    ?ResponseInterface $response = null,
                    ?Throwable $exception = null,
                ): bool => $retries < 1 && $exception instanceof ConnectException,
            ));
        }
        $stack->push(Middleware::history($this->history));

        return new AiServiceClient(
            new Client([
                'base_uri' => 'https://ai.test/',
                'timeout' => 20,
                'handler' => $stack,
            ]),
            new FrozenClock(self::NOW),
            $secret,
        );
    }

    /** @param array<string, mixed> $body */
    private static function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function assertSignature(RequestInterface $request, string $body): void
    {
        $timestamp = (string) (new FrozenClock(self::NOW))->now()->getTimestamp();
        self::assertSame($timestamp, $request->getHeaderLine('X-Timestamp'));
        self::assertSame(hash_hmac('sha256', $timestamp . '.' . $body, self::SECRET), $request->getHeaderLine('X-Signature'));
    }
}
