<?php

declare(strict_types=1);

namespace App\Public\Http;

use App\Shared\Exception\NotFoundException;
use App\Shared\Http\RequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class PublicController
{
    public function root(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response->withStatus(301)->withHeader('Location', '/en/');
    }

    public function languageHomeRedirect(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $locale = (string) $request->getAttribute(RequestAttributes::LOCALE);

        return $response->withStatus(301)->withHeader('Location', '/' . $locale . '/');
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = (string) $request->getAttribute(RequestAttributes::LOCALE);
        $view = Twig::fromRequest($request);

        return $view->render($response, 'home.html.twig', [
            'locale' => $locale,
            'csp_nonce' => $request->getAttribute(RequestAttributes::CSP_NONCE, ''),
            'canonical_path' => '/' . $locale . '/',
        ])->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function notFound(): never
    {
        throw new NotFoundException();
    }
}
