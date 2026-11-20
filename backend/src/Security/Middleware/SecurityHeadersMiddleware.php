<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Shared\Http\RequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 1 (spec 11): security headers from spec 11.3 on every response,
 * error responses included, plus the indexing rules from spec 12.5.
 *
 * A fresh CSP nonce is generated per request and exposed as the request
 * attribute "csp_nonce". The no-flash theme script (spec 14.4) is the one
 * inline script we allow, and it must carry that nonce.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $appEnv)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $nonce = base64_encode(random_bytes(16));
        $response = $handler->handle($request->withAttribute(RequestAttributes::CSP_NONCE, $nonce));

        $path = $request->getUri()->getPath();
        $devPage = $this->appEnv === 'local' && str_starts_with($path, '/_dev/');

        $headers = [
            'Content-Security-Policy' => $devPage ? self::devCsp($nonce) : self::csp($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // HSTS is meaningless over plain http and would pin localhost to https in some browsers.
        if ($this->appEnv !== 'local' && $this->appEnv !== 'testing') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if ($this->appEnv !== 'production' || self::isPrivatePath($path)) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private static function csp(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com",
            'frame-src https://challenges.cloudflare.com',
            "img-src 'self' data: https://res.cloudinary.com https://lh3.googleusercontent.com",
            "connect-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https://accounts.google.com",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * LOCAL ONLY, /_dev/ pages (the Swagger UI in DevApiDocsController):
     * Swagger UI loads from jsDelivr (pinned, with integrity hashes) and its
     * bundle needs 'unsafe-eval'. Never used outside APP_ENV=local.
     */
    private static function devCsp(string $nonce): string
    {
        $cdn = 'https://cdn.jsdelivr.net';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' {$cdn}",
            "style-src 'self' 'unsafe-inline' {$cdn}",
            "img-src 'self' data: {$cdn}",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /** The dashboard shell and the API are never indexed (spec 7, 12.5). */
    private static function isPrivatePath(string $path): bool
    {
        return $path === '/app' || str_starts_with($path, '/app/')
            || $path === '/api' || str_starts_with($path, '/api/');
    }
}
