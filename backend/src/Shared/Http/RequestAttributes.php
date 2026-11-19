<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Names of the PSR-7 request attributes the middleware chain sets.
 * Read them with $request->getAttribute(RequestAttributes::USER).
 */
final class RequestAttributes
{
    /** string: unique id for this request, also sent back as X-Request-Id */
    public const REQUEST_ID = 'request_id';

    /** string 'en'|'fr'|'es': the language to answer in */
    public const LOCALE = 'locale';

    /** string: per-request CSP nonce. Put it on inline scripts: <script nonce="{{ csp_nonce }}"> */
    public const CSP_NONCE = 'csp_nonce';

    /** the authenticated user, set by AuthMiddleware (B1) */
    public const USER = 'user';

    private function __construct()
    {
    }
}
