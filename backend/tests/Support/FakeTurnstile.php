<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Security\Turnstile\TurnstileVerifier;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;

/**
 * A real TurnstileVerifier whose HTTP client never leaves the machine:
 * Cloudflare always answers success, unless the token is "fail".
 */
final class FakeTurnstile
{
    public static function verifier(): TurnstileVerifier
    {
        $handler = static function (\Psr\Http\Message\RequestInterface $request): \GuzzleHttp\Promise\PromiseInterface {
            parse_str((string) $request->getBody(), $form);
            $ok = ($form['response'] ?? '') !== 'fail';

            return Create::promiseFor(new Response(200, [], (string) json_encode(['success' => $ok])));
        };

        return new TurnstileVerifier(
            new Client(['handler' => $handler]),
            '1x0000000000000000000000000000000AA', // test key: hostname/action checks skipped
            'localhost',
            'testing',
            new NullLogger(),
        );
    }
}
