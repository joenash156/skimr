<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Security\Middleware\SecurityHeadersMiddleware;
use App\Shared\Exception\NotFoundException;
use App\Shared\Http\DevApiDocsController;
use App\Shared\Http\RequestAttributes;
use App\Tests\AppTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/** The local-only Swagger UI must never exist outside APP_ENV=local. */
final class DevApiDocsTest extends AppTestCase
{
    private const CONTRACT = __DIR__ . '/../../../docs/api/openapi.yaml';

    public function testItIsA404OutsideLocal(): void
    {
        // The test suite runs with APP_ENV=testing, like staging/production would be "not local".
        self::assertSame(404, $this->request('GET', '/_dev/api-docs')->getStatusCode());
        self::assertSame(404, $this->request('GET', '/_dev/openapi.yaml')->getStatusCode());

        foreach (['testing', 'staging', 'production'] as $env) {
            try {
                (new DevApiDocsController($env, self::CONTRACT))->page($this->req('/_dev/api-docs'), new Response());
                self::fail('Expected 404 for ' . $env);
            } catch (NotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testLocallyItServesSwaggerUiWithIntegrityHashesAndTheContract(): void
    {
        $controller = new DevApiDocsController('local', self::CONTRACT);

        $page = (string) $controller->page($this->req('/_dev/api-docs')->withAttribute(RequestAttributes::CSP_NONCE, 'abc123'), new Response())->getBody();
        self::assertStringContainsString('swagger-ui-dist@' . DevApiDocsController::SWAGGER_VERSION, $page);
        self::assertSame(2, substr_count($page, 'integrity="sha384-'));
        self::assertStringContainsString('<script nonce="abc123">', $page);

        $yaml = (string) $controller->contract($this->req('/_dev/openapi.yaml'), new Response())->getBody();
        self::assertStringStartsWith('openapi:', $yaml);
    }

    public function testTheRelaxedCspAppliesOnlyToDevPagesInLocal(): void
    {
        self::assertStringContainsString('cdn.jsdelivr.net', $this->csp('local', '/_dev/api-docs'));
        self::assertStringNotContainsString('cdn.jsdelivr.net', $this->csp('local', '/api/v1/health'));
        self::assertStringNotContainsString('unsafe-eval', $this->csp('local', '/app'));
        foreach (['testing', 'staging', 'production'] as $env) {
            self::assertStringNotContainsString('cdn.jsdelivr.net', $this->csp($env, '/_dev/api-docs'));
            self::assertStringNotContainsString('unsafe-eval', $this->csp($env, '/_dev/api-docs'));
        }
    }

    private function csp(string $env, string $path): string
    {
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        return (new SecurityHeadersMiddleware($env))->process($this->req($path), $handler)->getHeaderLine('Content-Security-Policy');
    }

    private function req(string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost' . $path);
    }
}
