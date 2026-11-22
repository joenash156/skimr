<?php

declare(strict_types=1);

namespace App\Public\Http;

use App\Shared\Http\RequestAttributes;
use App\Shared\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LocaleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (preg_match('~^/([a-z]{2})(?:/|$)~', $path, $matches) === 1) {
            $locale = $matches[1];
            if (Translator::isSupported($locale)) {
                $request = $request->withAttribute(RequestAttributes::LOCALE, $locale);
            }
        }

        return $handler->handle($request);
    }
}
