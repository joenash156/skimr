<?php

declare(strict_types=1);

namespace App\Security\Turnstile;

use App\Security\Middleware\TurnstileMiddleware;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Checks a Cloudflare Turnstile token with the siteverify API (spec 11.2).
 * A token passes only when Cloudflare says success AND the hostname is our
 * APP_URL host AND the action matches the form (so a token solved on the
 * contact form can't be replayed on login). Cloudflare makes each token
 * single-use.
 *
 * Cloudflare's test secret keys (1x000…AA, 2x000…AA, 3x000…AA) skip the
 * hostname and action checks, because their responses carry dummy values.
 * They are refused outright when APP_ENV=production.
 *
 * Fails closed: no secret, a network error or a malformed reply all mean "not human".
 */
final class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const MAX_TOKEN_LENGTH = 2048;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly ?string $secretKey,
        private readonly string $expectedHostname,
        private readonly string $appEnv,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Middleware for one route. The action must match the `action` the
     * frontend passes to the Turnstile widget.
     *
     *     $app->post('/api/v1/contact', ...)->add($container->get(TurnstileVerifier::class)->middleware('contact'));
     */
    public function middleware(string $action): TurnstileMiddleware
    {
        return new TurnstileMiddleware($this, $action);
    }

    public function verify(?string $token, string $expectedAction, ?string $remoteIp = null): bool
    {
        if ($token === null || $token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return false;
        }
        if ($this->secretKey === null || $this->secretKey === '') {
            $this->logger->error('Turnstile: TURNSTILE_SECRET_KEY is not set; refusing every request.');

            return false;
        }

        $testKey = self::isTestSecret($this->secretKey);
        if ($testKey && $this->appEnv === 'production') {
            $this->logger->critical('Turnstile: a Cloudflare TEST secret key is configured in production; refusing every request.');

            return false;
        }

        $form = ['secret' => $this->secretKey, 'response' => $token];
        if ($remoteIp !== null) {
            $form['remoteip'] = $remoteIp;
        }

        try {
            $response = $this->http->request('POST', self::ENDPOINT, [
                'form_params' => $form,
                'timeout' => 5,
                'connect_timeout' => 3,
                'http_errors' => false,
            ]);
            $body = json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            $this->logger->warning('Turnstile: siteverify request failed: {error}', ['error' => $e->getMessage()]);

            return false;
        }

        if (!is_array($body) || ($body['success'] ?? false) !== true) {
            $codes = is_array($body) && is_array($body['error-codes'] ?? null) ? implode(',', $body['error-codes']) : 'malformed';
            $this->logger->info('Turnstile: token rejected ({codes}).', ['codes' => $codes]);

            return false;
        }
        if ($testKey) {
            return true;
        }

        if (($body['hostname'] ?? null) !== $this->expectedHostname) {
            $this->logger->warning('Turnstile: hostname mismatch.');

            return false;
        }
        if (($body['action'] ?? null) !== $expectedAction) {
            $this->logger->warning('Turnstile: action mismatch (expected {action}).', ['action' => $expectedAction]);

            return false;
        }

        return true;
    }

    private static function isTestSecret(string $secret): bool
    {
        return (bool) preg_match('/^[1-3]x0{31}AA$/', $secret);
    }
}
