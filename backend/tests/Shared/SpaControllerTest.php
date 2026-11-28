<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Http\JsonResponder;
use App\Shared\Http\RequestAttributes;
use App\Shared\Http\SpaController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class SpaControllerTest extends TestCase
{
    private string $index;

    protected function setUp(): void
    {
        $this->index = (string) tempnam(sys_get_temp_dir(), 'spa');
        file_put_contents(
            $this->index,
            '<meta name="turnstile-site-key" content="__TURNSTILE_SITE_KEY__"><script nonce="__CSP_NONCE__"></script>',
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->index);
    }

    public function testFillsTheNonceAndTheConfiguredTurnstileSiteKey(): void
    {
        $html = $this->render(' 0x4AAAAAAA-site "key" ');

        self::assertStringContainsString('nonce="abc123"', $html);
        // Trimmed and escaped: the key sits inside an HTML attribute.
        self::assertStringContainsString('content="0x4AAAAAAA-site &quot;key&quot;"', $html);
        self::assertStringNotContainsString('__TURNSTILE_SITE_KEY__', $html);
    }

    public function testKeepsThePlaceholderWithoutAKeySoTheFrontendUsesTheTestKey(): void
    {
        self::assertStringContainsString('content="__TURNSTILE_SITE_KEY__"', $this->render(''));
    }

    private function render(string $siteKey): string
    {
        $controller = new SpaController($this->index, new JsonResponder(), $siteKey);
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/app/login')
            ->withAttribute(RequestAttributes::CSP_NONCE, 'abc123');

        return (string) $controller($request, (new ResponseFactory())->createResponse())->getBody();
    }
}
