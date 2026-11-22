<?php

declare(strict_types=1);

namespace App\Public\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TrailingSlashMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (preg_match('~^/(?:en|fr|es)/$~', $path) === 1) {
            return $handler->handle($request);
        }

        if (!str_ends_with($path, '/')) {
            return $handler->handle($request);
        }

        $location = rtrim($path, '/');
        $query = $request->getUri()->getQuery();
        if ($query !== '') {
            $location .= '?' . $query;
        }

        return $this->responseFactory->createResponse(301)->withHeader('Location', $location);
    }
}
