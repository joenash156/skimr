<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Http\JsonResponder;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class JsonResponderTest extends TestCase
{
    private JsonResponder $responder;

    protected function setUp(): void
    {
        $this->responder = new JsonResponder();
    }

    public function testSuccessWrapsDataAndOmitsEmptyMeta(): void
    {
        $response = $this->responder->success(self::response(), ['id' => 'abc']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(['data' => ['id' => 'abc']], self::body($response));
    }

    public function testSuccessIncludesMetaWhenGiven(): void
    {
        $response = $this->responder->success(self::response(), [], ['note' => 'x'], 202);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame(['data' => [], 'meta' => ['note' => 'x']], self::body($response));
    }

    public function testPaginatedCapsPerPageAt100AndClampsValues(): void
    {
        $response = $this->responder->paginated(self::response(), [1, 2], 0, 500, -3);

        self::assertSame(
            ['data' => [1, 2], 'meta' => ['page' => 1, 'per_page' => 100, 'total' => 0]],
            self::body($response),
        );
    }

    public function testCreatedAndNoContent(): void
    {
        self::assertSame(201, $this->responder->created(self::response(), ['a' => 1])->getStatusCode());

        $empty = $this->responder->noContent(self::response());
        self::assertSame(204, $empty->getStatusCode());
        self::assertSame('', (string) $empty->getBody());
    }

    public function testErrorEnvelopeWithFieldsAndRequestId(): void
    {
        $response = $this->responder->error(
            self::response(),
            422,
            'VALIDATION_FAILED',
            'Please check the highlighted fields.',
            ['email' => 'This email is already registered.'],
            'req123',
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'Please check the highlighted fields.',
                'fields' => ['email' => 'This email is already registered.'],
                'request_id' => 'req123',
            ],
        ], self::body($response));
    }

    public function testErrorOmitsEmptyFieldsAndMissingRequestId(): void
    {
        $response = $this->responder->error(self::response(), 404, 'NOT_FOUND', 'Not found');

        self::assertSame(['error' => ['code' => 'NOT_FOUND', 'message' => 'Not found']], self::body($response));
    }

    public function testUnicodeAndSlashesAreNotEscaped(): void
    {
        $response = $this->responder->success(self::response(), ['t' => 'Résumé / Resumen']);

        self::assertStringContainsString('Résumé / Resumen', (string) $response->getBody());
    }

    private static function response(): ResponseInterface
    {
        return (new ResponseFactory())->createResponse();
    }

    /** @return array<mixed> */
    private static function body(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
