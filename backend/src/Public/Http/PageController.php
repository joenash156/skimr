<?php

declare(strict_types=1);

namespace App\Public\Http;

use App\Shared\Exception\NotFoundException;
use App\Shared\Http\RequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class PageController
{
    /** @var array<string, string> */
    private const PAGE_KEYS = [
        'features' => 'features',
        'how-it-works' => 'how_it_works',
        'about' => 'about',
        'privacy' => 'privacy',
        'terms' => 'terms',
        'cookies' => 'cookies',
    ];

    /**
     * @param array<string, string> $args
     */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $slug = $args['page'] ?? null;
        if ($request->getAttribute(RequestAttributes::LOCALE) !== 'en'
            || !is_string($slug)
            || !isset(self::PAGE_KEYS[$slug])) {
            throw new NotFoundException();
        }

        $pageKey = self::PAGE_KEYS[$slug];
        $data = [
            'locale' => 'en',
            'csp_nonce' => $request->getAttribute(RequestAttributes::CSP_NONCE, ''),
            'page_key' => $pageKey,
            'canonical_path' => '/en/' . $slug,
        ];

        if ($pageKey === 'features') {
            $data['features'] = self::features();
        }

        return Twig::fromRequest($request)->render($response, 'page.html.twig', $data)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @return list<array{id: string, key: string, tier: string}>
     */
    private static function features(): array
    {
        return [
            ['id' => 'F07', 'key' => 'source_processing', 'tier' => 'core'],
            ['id' => 'F08', 'key' => 'semantic_search', 'tier' => 'core'],
            ['id' => 'F09', 'key' => 'theme_clustering', 'tier' => 'core'],
            ['id' => 'F10', 'key' => 'methods_comparison', 'tier' => 'core'],
            ['id' => 'F11', 'key' => 'evidence_synthesis', 'tier' => 'core'],
            ['id' => 'F12', 'key' => 'admin_console', 'tier' => 'core'],
            ['id' => 'F13', 'key' => 'citation_map', 'tier' => 'extended'],
            ['id' => 'F14', 'key' => 'research_gaps', 'tier' => 'extended'],
            ['id' => 'F15', 'key' => 'source_chat', 'tier' => 'stretch'],
            ['id' => 'F16', 'key' => 'paper_discovery', 'tier' => 'extended'],
            ['id' => 'F17', 'key' => 'exports', 'tier' => 'extended'],
            ['id' => 'F18', 'key' => 'project_sharing', 'tier' => 'extended'],
            ['id' => 'F19', 'key' => 'public_syntheses', 'tier' => 'extended'],
            ['id' => 'F20', 'key' => 'usage_quotas', 'tier' => 'core'],
            ['id' => 'F21', 'key' => 'source_hunting', 'tier' => 'extended'],
            ['id' => 'F22', 'key' => 'image_ocr', 'tier' => 'extended'],
            ['id' => 'F23', 'key' => 'web_and_pasted_sources', 'tier' => 'extended'],
        ];
    }
}
