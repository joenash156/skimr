<?php

declare(strict_types=1);

namespace App\Auth\Google;

/**
 * The two calls to Google in the authorization-code + PKCE flow. Behind an
 * interface so tests can stand in for Google.
 */
interface GoogleClient
{
    /** False when GOOGLE_CLIENT_ID / SECRET / REDIRECT_URI aren't set. */
    public function isConfigured(): bool;

    /**
     * Builds the accounts.google.com URL to send the browser to.
     *
     * @return array{url: string, state: string, verifier: string} keep state and verifier for the callback
     */
    public function start(): array;

    /**
     * Swaps the authorization code for tokens and returns the verified identity.
     *
     * @throws GoogleAuthException
     */
    public function finish(string $code, string $verifier): GoogleIdentity;
}
