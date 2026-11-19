<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\Middleware\SecurityHeadersMiddleware;
use App\Shared\Http\RequestAttributes;
use App\Tests\AppTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class SecurityHeadersTest extends AppTestCase
{
    public function testEveryResponseCarriesTheSpecHeaders(): void
    {
        foreach (['/api/v1/health', '/api/v1/missing'] as $path) {
            $response = $this->request('GET', $path);

            $csp = $response->getHeaderLine('Content-Security-Policy');
            self::assertStringContainsString("default-src 'self'", $csp, $path);
            self::assertStringContainsString("frame-ancestors 'none'", $csp, $path);
            self::assertStringContainsString("object-src 'none'", $csp, $path);
            self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'), $path);
            self::assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'), $path);
            self::assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Opener-Policy'), $path);
            self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'), $path);
            self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/', $response->getHeaderLine('X-Request-Id'), $path);
        }
    }

    public function testProductionIndexesPublicPagesButNeverAppOrApi(): void
    {
        $middleware = new SecurityHeadersMiddleware('production');

        self::assertSame('', $this->through($middleware, '/en/features')->getHeaderLine('X-Robots-Tag'));
        self::assertSame('noindex, nofollow', $this->through($middleware, '/app/projects')->getHeaderLine('X-Robots-Tag'));
        self::assertSame('noindex, nofollow', $this->through($middleware, '/api/v1/health')->getHeaderLine('X-Robots-Tag'));
        self::assertSame('max-age=31536000; includeSubDomains', $this->through($middleware, '/')->getHeaderLine('Strict-Transport-Security'));
    }

    public function testStagingIsNeverIndexed(): void
    {
        $response = $this->through(new SecurityHeadersMiddleware('staging'), '/en/features');

        self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
    }

    public function testNoHstsLocally(): void
    {
        self::assertFalse($this->through(new SecurityHeadersMiddleware('local'), '/')->hasHeader('Strict-Transport-Security'));
    }

    public function testNonceIsFreshPerRequestAndMatchesTheCsp(): void
    {
        $middleware = new SecurityHeadersMiddleware('local');
        $seen = [];

        for ($i = 0; $i < 2; $i++) {
            $captured = null;
            $response = $this->through($middleware, '/', static function (ServerRequestInterface $r) use (&$captured): void {
                $captured = $r->getAttribute(RequestAttributes::CSP_NONCE);
            });
            self::assertIsString($captured);
            self::assertStringContainsString("'nonce-{$captured}'", $response->getHeaderLine('Content-Security-Policy'));
            $seen[] = $captured;
        }

        self::assertNotSame($seen[0], $seen[1]);
    }

    /**
     * @param (\Closure(ServerRequestInterface): void)|null $inspect
     */
    private function through(SecurityHeadersMiddleware $middleware, string $path, ?\Closure $inspect = null): ResponseInterface
    {
        $handler = new class ($inspect) implements RequestHandlerInterface {
            /** @param (\Closure(ServerRequestInterface): void)|null $inspect */
            public function __construct(private readonly ?\Closure $inspect)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->inspect !== null) {
                    ($this->inspect)($request);
                }

                return (new ResponseFactory())->createResponse();
            }
        };

        return $middleware->process(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://skimr.test' . $path),
            $handler,
        );
    }
}
