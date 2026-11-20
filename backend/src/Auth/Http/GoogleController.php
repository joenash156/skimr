<?php

declare(strict_types=1);

namespace App\Auth\Http;

use App\Auth\Google\GoogleAuthException;
use App\Auth\Google\GoogleAuthService;
use App\Auth\Google\GoogleClient;
use App\Auth\Google\GoogleIdentity;
use App\Auth\Google\GoogleSignInResult;
use App\Auth\Google\SignedCookie;
use App\Auth\RefreshCookie;
use App\Shared\Exception\InvalidTokenException;
use App\Shared\Http\ClientIp;
use App\Shared\Http\Input;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RequestAttributes;
use App\Shared\Translator;
use App\Users\UserPresenter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Google sign-in (P1 brief B1 task 12). The redirect and callback are browser
 * navigations and always answer with a 302 into the app, never JSON.
 */
final class GoogleController
{
    private const STATE_TTL = 600;
    private const LINK_TTL = 600;

    public function __construct(
        private readonly GoogleClient $google,
        private readonly GoogleAuthService $service,
        private readonly SignedCookie $stateCookie,
        private readonly SignedCookie $linkCookie,
        private readonly RefreshCookie $refreshCookie,
        private readonly CsrfGuard $csrf,
        private readonly UserPresenter $presenter,
        private readonly JsonResponder $json,
        private readonly LoggerInterface $logger,
        private readonly string $appUrl,
        private readonly int $accessTtl,
    ) {
    }

    /** GET /auth/google/redirect: remember state + PKCE verifier, send the browser to Google. */
    public function redirect(Request $request, Response $response): Response
    {
        if (!$this->google->isConfigured()) {
            $this->logger->error('Google sign-in requested but GOOGLE_CLIENT_ID / SECRET / REDIRECT_URI are not set.');

            return $this->toApp($response, '/app/login?error=google');
        }

        $start = $this->google->start();

        return $response
            ->withStatus(302)
            ->withHeader('Location', $start['url'])
            ->withHeader('Cache-Control', 'no-store')
            ->withAddedHeader('Set-Cookie', $this->stateCookie->issue(
                ['state' => $start['state'], 'verifier' => $start['verifier']],
                self::STATE_TTL,
            ));
    }

    /** GET /auth/google/callback: verify, then sign in, create the account, or ask to link. */
    public function callback(Request $request, Response $response): Response
    {
        // The state cookie is single-use: clear it whatever happens next.
        $response = $response->withAddedHeader('Set-Cookie', $this->stateCookie->clearHeader());

        try {
            $result = $this->service->signIn($this->identity($request), ClientIp::of($request), $request->getHeaderLine('User-Agent'), $this->locale($request));
        } catch (GoogleAuthException $e) {
            $this->logger->warning('Google sign-in refused: {reason}', ['reason' => $e->getMessage()]);

            return $this->toApp($response, '/app/login?error=google');
        }

        if ($result->kind === GoogleSignInResult::LINK_REQUIRED && $result->linkPayload !== null) {
            return $this->toApp(
                $response->withAddedHeader('Set-Cookie', $this->linkCookie->issue($result->linkPayload, self::LINK_TTL)),
                '/app/login?link=google',
            );
        }

        $session = $result->session ?? throw new \LogicException('Signed-in result without a session.');
        $target = $result->kind === GoogleSignInResult::NEW_ACCOUNT ? '/app/complete-profile' : '/app';

        return $this->toApp($this->refreshCookie->set($response, $session->refreshToken), $target);
    }

    /** POST /auth/google/link: confirm the password once to link Google to an existing account. */
    public function link(Request $request, Response $response): Response
    {
        $this->csrf->assert($request);

        $in = Input::fromRequest($request);
        $password = $in->secret('password');
        $in->validate();

        $payload = $this->linkCookie->read($request) ?? throw new InvalidTokenException();

        try {
            $session = $this->service->link($payload, $password, ClientIp::of($request), $request->getHeaderLine('User-Agent'));
        } catch (GoogleAuthException $e) {
            $this->logger->warning('Google link refused: {reason}', ['reason' => $e->getMessage()]);
            throw new InvalidTokenException($e);
        }

        $response = $this->json->success($response, [
            'access_token' => $session->accessToken->token,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl,
            'user' => $this->presenter->present($session->user),
        ]);

        return $this->refreshCookie->set($response, $session->refreshToken)
            ->withAddedHeader('Set-Cookie', $this->linkCookie->clearHeader())
            ->withHeader('Cache-Control', 'no-store');
    }

    /** @throws GoogleAuthException */
    private function identity(Request $request): GoogleIdentity
    {
        $query = $request->getQueryParams();
        if (isset($query['error'])) {
            throw new GoogleAuthException('Google returned an error (the user may have cancelled).');
        }

        $saved = $this->stateCookie->read($request);
        $state = $query['state'] ?? null;
        $code = $query['code'] ?? null;
        if (
            $saved === null
            || !is_string($saved['state'] ?? null) || !is_string($saved['verifier'] ?? null)
            || !is_string($state) || !hash_equals($saved['state'], $state)
        ) {
            throw new GoogleAuthException('Missing or mismatched OAuth state.');
        }
        if (!is_string($code) || $code === '' || strlen($code) > 2048) {
            throw new GoogleAuthException('Missing authorization code.');
        }

        return $this->google->finish($code, $saved['verifier']);
    }

    private function toApp(Response $response, string $path): Response
    {
        return $response
            ->withStatus(302)
            ->withHeader('Location', rtrim($this->appUrl, '/') . $path)
            ->withHeader('Cache-Control', 'no-store');
    }

    private function locale(Request $request): string
    {
        $locale = $request->getAttribute(RequestAttributes::LOCALE);
        if (is_string($locale) && Translator::isSupported($locale)) {
            return $locale;
        }

        return Translator::negotiate($request->getHeaderLine('Accept-Language')) ?? Translator::DEFAULT;
    }
}
