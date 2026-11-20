<?php

declare(strict_types=1);

namespace App\Auth\Google;

use League\OAuth2\Client\Provider\Google;

/**
 * league/oauth2-google with PKCE switched on (spec 5.2: authorization code
 * with PKCE). In league/oauth2-client 2.x, PKCE is enabled only by
 * overriding getPkceMethod(); a "pkceMethod" constructor option is silently
 * ignored, so this subclass is what actually turns it on. A test checks the
 * authorization URL carries code_challenge_method=S256.
 */
final class PkceGoogleProvider extends Google
{
    protected function getPkceMethod(): string
    {
        return self::PKCE_METHOD_S256;
    }
}
