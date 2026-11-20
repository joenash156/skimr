<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Auth\Google\GoogleAuthException;
use App\Auth\Google\GoogleClient;
use App\Auth\Google\GoogleIdentity;

/** Stands in for Google: each authorization code maps to a prepared identity. */
final class FakeGoogleClient implements GoogleClient
{
    public const STATE = 'state-1234567890';
    public const VERIFIER = 'verifier-abcdefghijklmnopqrstuvwxyz-0123456789';

    /** @var array<string, GoogleIdentity> */
    private array $identities = [];

    public function willReturn(string $code, GoogleIdentity $identity): void
    {
        $this->identities[$code] = $identity;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function start(): array
    {
        return [
            'url' => 'https://accounts.google.com/o/oauth2/v2/auth?state=' . self::STATE,
            'state' => self::STATE,
            'verifier' => self::VERIFIER,
        ];
    }

    public function finish(string $code, string $verifier): GoogleIdentity
    {
        if ($verifier !== self::VERIFIER) {
            throw new GoogleAuthException('PKCE verifier mismatch.');
        }

        return $this->identities[$code] ?? throw new GoogleAuthException('Unknown code.');
    }
}
