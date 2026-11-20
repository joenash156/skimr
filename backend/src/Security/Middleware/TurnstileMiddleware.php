<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Security\Turnstile\TurnstileVerifier;
use App\Shared\Exception\TurnstileFailedException;
use App\Shared\Http\ClientIp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 4 (spec 11): Cloudflare Turnstile, opt-in per route. Get one
 * from the verifier in your routes file:
 *
 *     $turnstile = $app->getContainer()->get(TurnstileVerifier::class);
 *     $app->post('/api/v1/contact', ContactController::class)->add($turnstile->middleware('contact'));
 *
 * Reads the token from the request body: `turnstile_token` (JSON API, see
 * the OpenAPI contract) or `cf-turnstile-response` (the field the widget adds
 * to plain HTML forms). Fails with 403 TURNSTILE_FAILED.
 */
final class TurnstileMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TurnstileVerifier $verifier,
        private readonly string $action,
    ) {
    }

    public function action(): string
    {
        return $this->action;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();
        $token = null;
        if (is_array($body)) {
            $candidate = $body['turnstile_token'] ?? $body['cf-turnstile-response'] ?? null;
            $token = is_string($candidate) ? $candidate : null;
        }

        if (!$this->verifier->verify($token, $this->action, ClientIp::of($request))) {
            throw new TurnstileFailedException();
        }

        return $handler->handle($request);
    }
}
