<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/skimr-i18n-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/auth.en.json', '{"auth":{"login":{"failed":"Wrong email or password"}}}');
        file_put_contents($this->dir . '/sources.en.json', '{"sources.upload.too_large":"Max {size} MB"}');
        file_put_contents($this->dir . '/auth.fr.json', '{"auth":{"login":{"failed":"E-mail ou mot de passe incorrect"}}}');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testMergesModuleFilesAndFlattensNestedKeys(): void
    {
        $t = new Translator($this->dir);

        self::assertSame('Wrong email or password', $t->trans('auth.login.failed'));
        self::assertSame('Max 10 MB', $t->trans('sources.upload.too_large', ['size' => 10]));
    }

    public function testFallsBackToEnglishThenToTheKey(): void
    {
        $t = new Translator($this->dir);

        self::assertSame('E-mail ou mot de passe incorrect', $t->trans('auth.login.failed', [], 'fr'));
        self::assertSame('Max 5 MB', $t->trans('sources.upload.too_large', ['size' => 5], 'fr'));
        self::assertSame('no.such.key', $t->trans('no.such.key', [], 'es'));
        self::assertSame('Wrong email or password', $t->trans('auth.login.failed', [], 'de'), 'unsupported locale uses English');
    }

    public function testHas(): void
    {
        $t = new Translator($this->dir);

        self::assertTrue($t->has('auth.login.failed', 'fr'));
        self::assertFalse($t->has('sources.upload.too_large', 'fr'));
    }

    /** @return iterable<string, array{?string, ?string}> */
    public static function acceptLanguages(): iterable
    {
        yield 'null' => [null, null];
        yield 'empty' => ['', null];
        yield 'region tag' => ['fr-CA', 'fr'];
        yield 'quality order' => ['de;q=1.0, es;q=0.8, en;q=0.9', 'en'];
        yield 'first wins ties' => ['es, fr', 'es'];
        yield 'q=0 is refused' => ['fr;q=0, en;q=0.5', 'en'];
        yield 'nothing supported' => ['de-DE, ja', null];
        yield 'wildcard ignored' => ['*', null];
    }

    #[DataProvider('acceptLanguages')]
    public function testNegotiate(?string $header, ?string $expected): void
    {
        self::assertSame($expected, Translator::negotiate($header));
    }
}
