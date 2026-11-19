<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Exception\BadRequestException;
use App\Shared\Exception\ConflictException;
use App\Shared\Exception\ForbiddenException;
use App\Shared\Exception\HttpException;
use App\Shared\Exception\NotFoundException;
use App\Shared\Exception\PayloadTooLargeException;
use App\Shared\Exception\QuotaExceededException;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Exception\ValidationException;
use App\Shared\Http\ErrorHandler;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RequestAttributes;
use App\Shared\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\AbstractLogger;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ErrorHandlerTest extends TestCase
{
    private string $translations;
    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->translations = sys_get_temp_dir() . '/skimr-errors-' . bin2hex(random_bytes(4));
        mkdir($this->translations);
        file_put_contents($this->translations . '/shared.en.json', json_encode(['errors' => [
            'not_found' => 'Not found',
            'server' => 'Server error',
            'validation' => 'Check the fields',
            'quota_exceeded' => 'Limit reached',
            'method_not_allowed' => 'Not allowed',
        ], 'validation' => ['email' => ['taken' => 'Email taken']]]));
        file_put_contents($this->translations . '/shared.fr.json', json_encode(['errors' => [
            'not_found' => 'Introuvable',
        ]]));
        $this->logger = new SpyLogger();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->translations . '/*') ?: []);
        rmdir($this->translations);
    }

    /**
     * @return iterable<string, array{HttpException, int, string}>
     */
    public static function domainExceptions(): iterable
    {
        yield 'validation' => [new ValidationException(), 422, 'VALIDATION_FAILED'];
        yield 'not found' => [new NotFoundException(), 404, 'NOT_FOUND'];
        yield 'forbidden' => [new ForbiddenException(), 403, 'FORBIDDEN'];
        yield 'unauthorized' => [new UnauthorizedException(), 401, 'UNAUTHENTICATED'];
        yield 'conflict' => [new ConflictException(), 409, 'CONFLICT'];
        yield 'quota' => [new QuotaExceededException(), 429, 'QUOTA_EXCEEDED'];
        yield 'too large' => [new PayloadTooLargeException(), 413, 'PAYLOAD_TOO_LARGE'];
        yield 'bad request' => [new BadRequestException(), 400, 'BAD_REQUEST'];
    }

    #[DataProvider('domainExceptions')]
    public function testMapsDomainExceptionToStatusAndCode(HttpException $exception, int $status, string $code): void
    {
        $response = $this->handle($exception);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($code, self::error($response)['code']);
        self::assertSame('req-1', self::error($response)['request_id']);
        self::assertSame([], $this->logger->records, '4xx errors are expected and must not be logged as errors');
    }

    public function testUnknownExceptionBecomesOpaque500AndIsLogged(): void
    {
        $response = $this->handle(new \RuntimeException('SQLSTATE secret detail'));

        self::assertSame(500, $response->getStatusCode());
        $error = self::error($response);
        self::assertSame('SERVER_ERROR', $error['code']);
        self::assertSame('Server error', $error['message']);
        self::assertArrayNotHasKey('debug', $error);
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
        self::assertStringNotContainsString('.php', (string) $response->getBody());

        self::assertCount(1, $this->logger->records);
        self::assertSame('req-1', $this->logger->records[0]['context']['request_id']);
    }

    public function testDebugModeAddsDetailsOnlyFor500(): void
    {
        $response = $this->handle(new \RuntimeException('boom'), display: true);

        $debug = self::error($response)['debug'];
        self::assertIsArray($debug);
        self::assertSame('boom', $debug['message']);

        $notFound = $this->handle(new NotFoundException(), display: true);
        self::assertArrayNotHasKey('debug', self::error($notFound));
    }

    public function testValidationFieldsAreTranslated(): void
    {
        $response = $this->handle(new ValidationException(['email' => 'validation.email.taken']));

        self::assertSame(['email' => 'Email taken'], self::error($response)['fields']);
    }

    public function testQuotaExceededSendsRetryAfter(): void
    {
        $response = $this->handle(new QuotaExceededException(120));

        self::assertSame('120', $response->getHeaderLine('Retry-After'));
        self::assertSame('60', $this->handle(QuotaExceededException::rateLimited(60))->getHeaderLine('Retry-After'));
    }

    public function testMessageFollowsAcceptLanguageThenFallsBackToEnglish(): void
    {
        $request = self::request()->withHeader('Accept-Language', 'fr-CA,fr;q=0.9');

        self::assertSame('Introuvable', self::error($this->handle(new NotFoundException(), request: $request))['message']);
        // No French string for this key: English is used instead of the raw key.
        self::assertSame('Server error', self::error($this->handle(new \LogicException(), request: $request))['message']);
    }

    public function testLocaleAttributeBeatsAcceptLanguage(): void
    {
        $request = self::request()
            ->withHeader('Accept-Language', 'en')
            ->withAttribute(RequestAttributes::LOCALE, 'fr');

        self::assertSame('Introuvable', self::error($this->handle(new NotFoundException(), request: $request))['message']);
    }

    public function testSlimRoutingErrorsAreMapped(): void
    {
        $request = self::request();

        $notFound = $this->handle(new HttpNotFoundException($request), request: $request);
        self::assertSame(404, $notFound->getStatusCode());
        self::assertSame('NOT_FOUND', self::error($notFound)['code']);

        $notAllowed = (new HttpMethodNotAllowedException($request))->setAllowedMethods(['GET', 'HEAD']);
        $response = $this->handle($notAllowed, request: $request);
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
    }

    public function testHtmlRendererIsUsedOnlyOutsideTheApi(): void
    {
        $factory = new ResponseFactory();
        $renderer = static function (ServerRequestInterface $request, int $status) use ($factory): ResponseInterface {
            $response = $factory->createResponse($status);
            $response->getBody()->write('<h1>Page not found</h1>');

            return $response->withHeader('Content-Type', 'text/html');
        };

        $public = $this->handle(new NotFoundException(), request: self::request('/en/missing'), renderer: $renderer);
        self::assertSame('<h1>Page not found</h1>', (string) $public->getBody());
        self::assertSame(404, $public->getStatusCode());

        $api = $this->handle(new NotFoundException(), request: self::request('/api/v1/missing'), renderer: $renderer);
        self::assertSame('NOT_FOUND', self::error($api)['code']);
    }

    private function handle(
        \Throwable $exception,
        bool $display = false,
        ?ServerRequestInterface $request = null,
        ?\Closure $renderer = null,
    ): ResponseInterface {
        $handler = new ErrorHandler(
            new ResponseFactory(),
            new JsonResponder(),
            new Translator($this->translations),
            $this->logger,
            $renderer,
        );

        return $handler($request ?? self::request(), $exception, $display, true, false);
    }

    private static function request(string $path = '/api/v1/thing'): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://localhost' . $path)
            ->withAttribute(RequestAttributes::REQUEST_ID, 'req-1');
    }

    /** @return array<string, mixed> */
    private static function error(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['error']);

        return $body['error'];
    }
}

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /** @param array<mixed> $context */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
