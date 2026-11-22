<?php

declare(strict_types=1);

namespace App\Public\Http;

use App\Shared\Http\RequestAttributes;
use App\Shared\Translator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class PublicErrorRenderer
{
    public function __construct(
        private readonly Twig $twig,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function render(ServerRequestInterface $request, int $status): ?ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (preg_match('~^/(?:[a-z]{2}(?:/|$)|robots\.txt$|sitemap(?:-[a-z]+)?\.xml$)~', $path) !== 1
            && $path !== '/') {
            return null;
        }

        $locale = $request->getAttribute(RequestAttributes::LOCALE);
        if (!is_string($locale) || !Translator::isSupported($locale)) {
            $locale = Translator::DEFAULT;
        }

        $response = $this->responseFactory->createResponse($status);

        return $this->twig->render($response, 'error.html.twig', [
            'locale' => $locale,
            'csp_nonce' => $request->getAttribute(RequestAttributes::CSP_NONCE, ''),
            'status' => $status,
        ])->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
