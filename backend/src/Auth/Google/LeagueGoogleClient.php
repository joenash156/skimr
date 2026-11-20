<?php

declare(strict_types=1);

namespace App\Auth\Google;

use App\Shared\Clock\Clock;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;

/**
 * GoogleClient on league/oauth2-google (spec 8), with PKCE S256.
 *
 * The id_token is checked for aud (our client id), iss (Google), exp and the
 * presence of sub and email (spec 11.2). Its signature isn't re-verified:
 * it comes straight from Google's token endpoint over TLS, authenticated
 * with our client secret, which OpenID Connect Core 3.1.3.7 accepts in place
 * of a signature check.
 */
final class LeagueGoogleClient implements GoogleClient
{
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $redirectUri,
        private readonly Clock $clock,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== null && $this->clientId !== ''
            && $this->clientSecret !== null && $this->clientSecret !== ''
            && $this->redirectUri !== null && $this->redirectUri !== '';
    }

    public function start(): array
    {
        $provider = $this->provider();
        $url = $provider->getAuthorizationUrl(['prompt' => 'select_account']);

        return ['url' => $url, 'state' => $provider->getState(), 'verifier' => (string) $provider->getPkceCode()];
    }

    public function finish(string $code, string $verifier): GoogleIdentity
    {
        $provider = $this->provider();
        $provider->setPkceCode($verifier);

        try {
            $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
        } catch (IdentityProviderException | \UnexpectedValueException | \GuzzleHttp\Exception\GuzzleException $e) {
            throw new GoogleAuthException('Token exchange failed: ' . $e->getMessage(), 0, $e);
        }
        if (!$token instanceof AccessToken) {
            throw new GoogleAuthException('Unexpected token response.');
        }

        $idToken = $token->getValues()['id_token'] ?? null;
        if (!is_string($idToken)) {
            throw new GoogleAuthException('No id_token in the token response.');
        }

        return $this->identityFrom($idToken);
    }

    /** Validates the id_token claims and builds the identity. Public for tests. */
    public function identityFrom(string $idToken): GoogleIdentity
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new GoogleAuthException('Malformed id_token.');
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        if (!is_array($claims)) {
            throw new GoogleAuthException('Unreadable id_token claims.');
        }

        if (($claims['aud'] ?? null) !== $this->clientId) {
            throw new GoogleAuthException('id_token audience mismatch.');
        }
        if (!in_array($claims['iss'] ?? null, self::ISSUERS, true)) {
            throw new GoogleAuthException('id_token issuer mismatch.');
        }
        if (!is_int($claims['exp'] ?? null) || $claims['exp'] <= $this->clock->now()->getTimestamp()) {
            throw new GoogleAuthException('id_token expired.');
        }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 64) {
            throw new GoogleAuthException('id_token has no usable sub.');
        }
        if (!is_string($claims['email'] ?? null) || filter_var($claims['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleAuthException('id_token has no usable email.');
        }

        $verified = $claims['email_verified'] ?? false;

        return new GoogleIdentity(
            $claims['sub'],
            mb_strtolower($claims['email']),
            $verified === true || $verified === 'true',
            is_string($claims['given_name'] ?? null) ? $claims['given_name'] : null,
            is_string($claims['family_name'] ?? null) ? $claims['family_name'] : null,
        );
    }

    private function provider(): PkceGoogleProvider
    {
        if (!$this->isConfigured()) {
            throw new GoogleAuthException('Google sign-in is not configured (GOOGLE_CLIENT_ID / SECRET / REDIRECT_URI).');
        }

        return new PkceGoogleProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $this->redirectUri,
        ]);
    }
}
