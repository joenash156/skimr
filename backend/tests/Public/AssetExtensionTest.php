<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Public\Twig\AssetExtension;
use PHPUnit\Framework\TestCase;

final class AssetExtensionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/asset-ext-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/assets', 0777, true);
        file_put_contents($this->dir . '/assets/site.css', 'body{}');
        touch($this->dir . '/assets/site.css', 1764243200);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/assets/site.css');
        @rmdir($this->dir . '/assets');
        @rmdir($this->dir);
    }

    public function testAddsTheFileTimeAsAVersion(): void
    {
        $assets = new AssetExtension($this->dir);

        self::assertSame('/assets/site.css?v=1764243200', $assets->url('/assets/site.css'));
    }

    public function testLeavesMissingOrUnsafePathsUnversioned(): void
    {
        $assets = new AssetExtension($this->dir);

        self::assertSame('/assets/missing.js', $assets->url('/assets/missing.js'));
        self::assertSame('/../assets/site.css', $assets->url('/../assets/site.css'));
        self::assertSame('https://cdn.example/x.js', $assets->url('https://cdn.example/x.js'));
    }
}
