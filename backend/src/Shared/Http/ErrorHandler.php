<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Exception\HttpException;
use App\Shared\Translator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException as SlimHttpException;
use Slim\Exception\HttpMethodNotAllowedException;

/**
 * Slim's default error handler: the single place where exceptions become
 * HTTP responses (spec 18.1). Domain exceptions map to their status and
 * code; anything unexpected becomes a 500 that reveals nothing unless
 * APP_DEBUG is on. Every error carries the request id so a user can
 * quote it and we can find the matching log line.
 */
final class ErrorHandler
{
    /** @var array<int, array{string, string}> status => [code, message key] for framework errors */
    private const FRAMEWORK_ERRORS = [
        400 => ['BAD_REQUEST', 'errors.bad_request'],
        401 => ['UNAUTHENTICATED', 'errors.unauthenticated'],
        403 => ['FORBIDDEN', 'errors.forbidden'],
        404 => ['NOT_FOUND', 'errors.not_found'],
        405 => ['METHOD_NOT_ALLOWED', 'errors.method_not_allowed'],
        413 => ['PAYLOAD_TOO_LARGE', 'errors.payload_too_large'],
        429 => ['RATE_LIMITED', 'errors.rate_limited'],
    ];

    /**
     * @param (\Closure(ServerRequestInterface, int): ?ResponseInterface)|null $htmlRenderer
     *        optional renderer for non-API paths (the public site's 404/500 pages).
     *        Return null to fall back to JSON.
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly JsonResponder $responder,
        private readonly Translator $translator,
        private readonly LoggerInterface $logger,
        private readonly ?\Closure $htmlRenderer = null,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $requestId = self::requestId($request);
        $locale = self::locale($request);

        [$status, $code, $messageKey, $params, $fieldKeys, $headers] = $this->classify($exception);

        if ($status >= 500 && $logErrors) {
            $this->logger->error('Unhandled exception', [
                'request_id' => $requestId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'location' => $exception->getFile() . ':' . $exception->getLine(),
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
            ] + ($logErrorDetails ? ['trace' => $exception->getTraceAsString()] : []));
        }

        if ($this->htmlRenderer !== null && !self::isApiPath($request)) {
            $html = ($this->htmlRenderer)($request, $status);
            if ($html !== null) {
                return self::withHeaders($html, $headers);
            }
        }

        $fields = [];
        foreach ($fieldKeys as $field => $key) {
            $fields[$field] = $this->translator->trans($key, [], $locale);
        }

        $extra = [];
        if ($displayErrorDetails && $status >= 500) {
            $extra['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'location' => $exception->getFile() . ':' . $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        $response = $this->responder->error(
            $this->responseFactory->createResponse(),
            $status,
            $code,
            $this->translator->trans($messageKey, $params, $locale),
            $fields,
            $requestId,
            $extra,
        );

        return self::withHeaders($response, $headers);
    }

    /**
     * @return array{int, string, string, array<string, string|int|float>, array<string, string>, array<string, string>}
     */
    private function classify(\Throwable $exception): array
    {
        if ($exception instanceof HttpException) {
            return [
                $exception->status(),
                $exception->errorCode(),
                $exception->messageKey(),
                $exception->params(),
                $exception->fields(),
                $exception->headers(),
            ];
        }

        if ($exception instanceof SlimHttpException) {
            $status = $exception->getCode();
            [$code, $key] = self::FRAMEWORK_ERRORS[$status] ?? ['SERVER_ERROR', 'errors.server'];
            $headers = $exception instanceof HttpMethodNotAllowedException
                ? ['Allow' => implode(', ', $exception->getAllowedMethods())]
                : [];

            return [$status >= 400 && $status < 600 ? $status : 500, $code, $key, [], [], $headers];
        }

        return [500, 'SERVER_ERROR', 'errors.server', [], [], []];
    }

    private static function requestId(ServerRequestInterface $request): ?string
    {
        $id = $request->getAttribute(RequestAttributes::REQUEST_ID);

        return is_string($id) ? $id : null;
    }

    /** Language order for API messages (spec 10.1): the user's locale, then Accept-Language, then English. */
    private static function locale(ServerRequestInterface $request): string
    {
        $locale = $request->getAttribute(RequestAttributes::LOCALE);
        if (is_string($locale) && Translator::isSupported($locale)) {
            return $locale;
        }

        return Translator::negotiate($request->getHeaderLine('Accept-Language')) ?? Translator::DEFAULT;
    }

    private static function isApiPath(ServerRequestInterface $request): bool
    {
        return str_starts_with($request->getUri()->getPath(), '/api/');
    }

    /**
     * @param array<string, string> $headers
     */
    private static function withHeaders(ResponseInterface $response, array $headers): ResponseInterface
    {
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
