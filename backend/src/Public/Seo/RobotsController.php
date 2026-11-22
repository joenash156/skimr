<?php

declare(strict_types=1);

namespace App\Public\Seo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RobotsController
{
    private readonly string $siteUrl;

    public function __construct(string $siteUrl)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /app/',
            'Disallow: /api/',
            'Sitemap: ' . $this->siteUrl . '/sitemap.xml',
            '',
        ]));

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
