<?php

declare(strict_types=1);

namespace App\Public\Hero;

/** One home-page carousel slide, already resolved for the visitor's language. */
final readonly class HeroSlide
{
    public function __construct(
        public string $imageUrl,
        public string $heading,
        public string $alt,
    ) {
    }
}
