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
 *
 * It also fills <meta name="turnstile-site-key" content="__TURNSTILE_SITE_KEY__">
 * with TURNSTILE_SITE_KEY (public, not a secret). Without this the build would
 * fall back to Cloudflare's test key, which the real secret rejects, so nobody
 * could log in or sign up in production.
 */
final class SpaController
{
    public const NONCE_PLACEHOLDER = '__CSP_NONCE__';
    public const TURNSTILE_PLACEHOLDER = '__TURNSTILE_SITE_KEY__';

    public function __construct(
        private readonly string $indexFile,
        private readonly JsonResponder $responder,
        private readonly string $turnstileSiteKey = '',
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!is_file($this->indexFile)) {
            return $this->responder->error(
                $response,
                503,
                'FRONTEND_NOT_BUILT',
                'The dashboard has not been built. Run "pnpm build" in frontend/, or use the Vite dev server on http://localhost:5173/app/.',
            );
        }

        $nonce = $request->getAttribute(RequestAttributes::CSP_NONCE);
        $replacements = [self::NONCE_PLACEHOLDER => is_string($nonce) ? $nonce : ''];
        // No key configured (local): keep the placeholder, so the frontend uses the test key.
        if (trim($this->turnstileSiteKey) !== '') {
            $replacements[self::TURNSTILE_PLACEHOLDER] = htmlspecialchars(trim($this->turnstileSiteKey), ENT_QUOTES);
        }
        $html = strtr((string) file_get_contents($this->indexFile), $replacements);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache');
    }
}
