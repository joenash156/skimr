<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app and /app/*: returns the built React index.html (spec 7) and lets
 * React Router take over. Real files under /app/assets are served by the
 * web server directly and never reach this controller.
 *
 * The inline no-flash theme script in index.html must be written as
 * <script nonce="__CSP_NONCE__">; this controller swaps in the request's
 * nonce so the CSP (spec 11.3) allows it.
 */
final class SpaController
{
    public const NONCE_PLACEHOLDER = '__CSP_NONCE__';

    public function __construct(
        private readonly string $indexFile,
        private readonly JsonResponder $responder,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!is_file($this->indexFile)) {
            return $this->responder->error(
                $response,
                503,
                'FRONTEND_NOT_BUILT',
                'The dashboard has not been built. Run "npm run build" in frontend/, or use the Vite dev server on http://localhost:5173/app/.',
            );
        }

        $nonce = $request->getAttribute(RequestAttributes::CSP_NONCE);
        $html = str_replace(
            self::NONCE_PLACEHOLDER,
            is_string($nonce) ? $nonce : '',
            (string) file_get_contents($this->indexFile),
        );

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache');
    }
}
