<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Exception\NotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * LOCAL DEVELOPMENT ONLY: an interactive Swagger UI for docs/api/openapi.yaml
 * at http://localhost:8080/_dev/api-docs. "Try it out" calls the real local
 * API from the same origin, so cookies (refresh, logout) work with no CORS.
 *
 * Every other APP_ENV (testing, staging, production) answers 404, so it can
 * never be exposed on a server. SecurityHeadersMiddleware relaxes the CSP for
 * /_dev/ pages, again only when APP_ENV=local.
 *
 * Swagger UI comes from jsDelivr pinned to one version, with Subresource
 * Integrity hashes: the browser refuses the files if they differ by a byte.
 */
final class DevApiDocsController
{
    public const SWAGGER_VERSION = '5.17.14';
    private const CSS_SRI = 'sha384-wxLW6kwyHktdDGr6Pv1zgm/VGJh99lfUbzSn6HNHBENZlCN7W602k9VkGdxuFvPn';
    private const JS_SRI = 'sha384-wmyclcVGX/WhUkdkATwhaK1X1JtiNrr2EoYJ+diV3vj4v6OC5yCeSu+yW13SYJep';

    public function __construct(
        private readonly string $appEnv,
        private readonly string $contractFile,
    ) {
    }

    /** GET /_dev/api-docs */
    public function page(Request $request, Response $response): Response
    {
        $this->assertLocal();

        $nonce = (string) $request->getAttribute(RequestAttributes::CSP_NONCE, '');
        $cdn = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@' . self::SWAGGER_VERSION;
        $n = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
        $cssSri = self::CSS_SRI;
        $jsSri = self::JS_SRI;

        $html = <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <title>Skimr API (local)</title>
              <link rel="stylesheet" href="{$cdn}/swagger-ui.css" integrity="{$cssSri}" crossorigin="anonymous">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="{$cdn}/swagger-ui-bundle.js" integrity="{$jsSri}" crossorigin="anonymous"></script>
              <script nonce="{$n}">
                window.ui = SwaggerUIBundle({
                  url: '/_dev/openapi.yaml',
                  dom_id: '#swagger-ui',
                  deepLinking: true,
                  persistAuthorization: true,
                  tryItOutEnabled: true,
                  withCredentials: true,
                  displayRequestDuration: true,
                });
              </script>
            </body>
            </html>
            HTML;

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }

    /** GET /_dev/openapi.yaml: the contract, as the page loads it. */
    public function contract(Request $request, Response $response): Response
    {
        $this->assertLocal();

        $yaml = @file_get_contents($this->contractFile);
        if ($yaml === false) {
            throw new NotFoundException();
        }
        $response->getBody()->write($yaml);

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }

    private function assertLocal(): void
    {
        if ($this->appEnv !== 'local') {
            throw new NotFoundException();
        }
    }
}
