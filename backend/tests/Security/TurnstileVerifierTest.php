<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\Turnstile\TurnstileVerifier;
use App\Shared\Exception\TurnstileFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;

final class TurnstileVerifierTest extends TestCase
{
    private const REAL_SECRET = '0x4AAAAAAAreal-looking-secret-key';
    private const TEST_SECRET = '1x0000000000000000000000000000000AA';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    /** @param list<ResponseInterface|\Throwable> $replies */
    private function verifier(array $replies, ?string $secret = self::REAL_SECRET, string $env = 'staging'): TurnstileVerifier
    {
        $stack = HandlerStack::create(new MockHandler($replies));
        $this->sent = [];
        $stack->push(Middleware::history($this->sent));

        return new TurnstileVerifier(new Client(['handler' => $stack]), $secret, 'skimr.foliony.app', $env, new NullLogger());
    }

    /** @param array<string, mixed> $body */
    private static function reply(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body));
    }

    public function testPassesWhenSuccessHostnameAndActionMatch(): void
    {
        $verifier = $this->verifier([self::reply(['success' => true, 'hostname' => 'skimr.foliony.app', 'action' => 'login'])]);

        self::assertTrue($verifier->verify('tok', 'login', '203.0.113.10'));

        parse_str((string) $this->sent[0]['request']->getBody(), $form);
        self::assertSame(['secret' => self::REAL_SECRET, 'response' => 'tok', 'remoteip' => '203.0.113.10'], $form);
    }

    public function testFailsOnWrongHostnameWrongActionOrNoSuccess(): void
    {
        self::assertFalse($this->verifier([self::reply(['success' => true, 'hostname' => 'evil.example', 'action' => 'login'])])->verify('tok', 'login'));
        self::assertFalse($this->verifier([self::reply(['success' => true, 'hostname' => 'skimr.foliony.app', 'action' => 'contact'])])->verify('tok', 'login'));
        self::assertFalse($this->verifier([self::reply(['success' => false, 'error-codes' => ['timeout-or-duplicate']])])->verify('tok', 'login'));
        self::assertFalse($this->verifier([new Response(200, [], 'not json')])->verify('tok', 'login'));
    }

    public function testFailsClosedOnNetworkErrorsMissingTokenOrMissingSecret(): void
    {
        $down = new ConnectException('down', new Request('POST', 'https://challenges.cloudflare.com'));
        self::assertFalse($this->verifier([$down])->verify('tok', 'login'));

        self::assertFalse($this->verifier([])->verify(null, 'login'));
        self::assertFalse($this->verifier([])->verify('', 'login'));
        self::assertFalse($this->verifier([])->verify(str_repeat('x', 3000), 'login'));
        self::assertFalse($this->verifier([], null)->verify('tok', 'login'));
        self::assertSame([], $this->sent, 'Nothing should be sent to Cloudflare for these.');
    }

    public function testCloudflareTestKeysSkipHostnameAndActionButNotInProduction(): void
    {
        $dummy = ['success' => true, 'hostname' => 'example.com', 'action' => 'test'];

        self::assertTrue($this->verifier([self::reply($dummy)], self::TEST_SECRET, 'local')->verify('XXXX.DUMMY.TOKEN.XXXX', 'login'));
        self::assertFalse($this->verifier([self::reply($dummy)], self::TEST_SECRET, 'production')->verify('XXXX.DUMMY.TOKEN.XXXX', 'login'));
    }

    public function testMiddlewareReadsTheJsonFieldOrTheHtmlFormFieldAndThrows403(): void
    {
        $ok = self::reply(['success' => true, 'hostname' => 'skimr.foliony.app', 'action' => 'contact']);
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new \Slim\Psr7\Response(204);
            }
        };
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/contact');

        $json = $this->verifier([$ok])->middleware('contact')->process($request->withParsedBody(['turnstile_token' => 't']), $handler);
        self::assertSame(204, $json->getStatusCode());

        $form = $this->verifier([$ok])->middleware('contact')->process($request->withParsedBody(['cf-turnstile-response' => 't']), $handler);
        self::assertSame(204, $form->getStatusCode());

        $this->expectException(TurnstileFailedException::class);
        $this->verifier([])->middleware('contact')->process($request->withParsedBody([]), $handler);
    }
}
