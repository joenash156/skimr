<?php

declare(strict_types=1);

namespace App\Public\Seo;

use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Argument\Text\FontFamily;
use Cloudinary\Transformation\Argument\Text\FontWeight;
use Cloudinary\Transformation\Delivery;
use Cloudinary\Transformation\Format;
use Cloudinary\Transformation\Overlay;
use Cloudinary\Transformation\Position;
use Cloudinary\Transformation\Resize;
use Cloudinary\Transformation\Source;
use Cloudinary\Transformation\TextStyle;
use Cloudinary\Transformation\Transformation;
use InvalidArgumentException;
use RuntimeException;

final class OgImageBuilder
{
    private const TEMPLATE_PUBLIC_ID = 'skimr/site/og-template-v1';

    private readonly string $siteUrl;

    private readonly ?Cloudinary $cloudinary;

    public function __construct(string $siteUrl, string $environment, ?Cloudinary $cloudinary)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $isLocalEnvironment = in_array($environment, ['local', 'testing'], true);
        if (!$isLocalEnvironment && $cloudinary === null) {
            throw new RuntimeException('CLOUDINARY_URL is required for public OG images outside local development.');
        }

        $this->cloudinary = $isLocalEnvironment ? null : $cloudinary;
    }

    public function forTitle(string $title): string
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('An OG image title is required.');
        }

        if ($this->cloudinary === null) {
            return $this->siteUrl . '/assets/og-default.svg';
        }

        $transformation = (new Transformation())
            ->resize(Resize::fill(1200, 630))
            ->addAction(
                Overlay::source(
                    Source::text($title)
                        ->textStyle(
                            (new TextStyle(FontFamily::ARIAL, 58))
                                ->fontWeight(FontWeight::BOLD),
                        )
                        ->textFit(950, 240)
                        ->textColor('#FFFFFF')
                        ->disallowOverflow(),
                )->position(Position::absolute(125, 220)),
            )
            ->delivery(Delivery::format(Format::jpg()));

        $image = $this->cloudinary->image(self::TEMPLATE_PUBLIC_ID);

        return (string) $image->toUrl($transformation);
    }
}
