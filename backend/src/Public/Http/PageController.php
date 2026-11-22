<?php

declare(strict_types=1);

namespace App\Public\Http;

use App\Public\Seo\JsonLd;
use App\Public\Seo\MetaBuilder;
use App\Public\Seo\PublicPageMap;
use App\Shared\Exception\NotFoundException;
use App\Shared\Http\RequestAttributes;
use App\Shared\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class PageController
{
    public function __construct(
        private readonly MetaBuilder $metaBuilder,
        private readonly JsonLd $jsonLd,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $locale = $request->getAttribute(RequestAttributes::LOCALE);
        $slug = $args['page'] ?? null;
        if (!is_string($locale)
            || !Translator::isSupported($locale)
            || !is_string($slug)
            || ($pageKey = PublicPageMap::pageKeyFor($locale, $slug)) === null) {
            throw new NotFoundException();
        }

        $data = [
            'locale' => $locale,
            'csp_nonce' => $request->getAttribute(RequestAttributes::CSP_NONCE, ''),
            'page_key' => $pageKey,
            'metadata' => $this->metaBuilder->forPage($pageKey, $locale),
            'jsonld' => $this->jsonLd->forLocale($locale),
            'page_urls' => PublicPageMap::urlsForLocale($locale),
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
