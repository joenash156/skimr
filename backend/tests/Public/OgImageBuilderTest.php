<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Public\Seo\OgImageBuilder;
use Cloudinary\Cloudinary;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OgImageBuilderTest extends TestCase
{
    public function testProductionUrlUsesCloudinaryWithPageTitleAndOpenGraphDimensions(): void
    {
        $cloudinary = new Cloudinary(['cloud' => ['cloud_name' => 'demo']]);
        $builder = new OgImageBuilder('https://skimr.example', 'production', $cloudinary);

        $url = $builder->forTitle('About Skimr | Evidence-led research');

        self::assertStringStartsWith('https://res.cloudinary.com/demo/image/upload/', $url);
        self::assertStringContainsString('c_fill,h_630,w_1200', $url);
        self::assertStringContainsString('l_text:Arial_58_bold:', $url);
        self::assertStringContainsString('About%20Skimr', $url);
        self::assertStringContainsString('f_jpg', $url);
        self::assertStringContainsString('/skimr/site/og-template-v1', $url);
    }

    public function testLocalEnvironmentUsesStaticFallbackWhenCloudinaryIsNotConfigured(): void
    {
        $builder = new OgImageBuilder('http://localhost:8080', 'local', null);

        self::assertSame(
            'http://localhost:8080/assets/og-default.svg',
            $builder->forTitle('Skimr'),
        );
    }

    public function testDeployEnvironmentRequiresCloudinaryConfiguration(): void
    {
        $this->expectException(RuntimeException::class);

        new OgImageBuilder('https://skimr.example', 'production', null);
    }

    public function testTitleCannotBeEmpty(): void
    {
        $builder = new OgImageBuilder('http://localhost:8080', 'testing', null);

        $this->expectException(InvalidArgumentException::class);
        $builder->forTitle('  ');
    }
}
