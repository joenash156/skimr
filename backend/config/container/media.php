<?php

declare(strict_types=1);

use App\Media\CloudinaryService;
use App\Shared\Clock\Clock;
use Cloudinary\Cloudinary;
use Psr\Container\ContainerInterface;

return [
    CloudinaryService::class => static function (ContainerInterface $container): CloudinaryService {
        /** @var array{cloudinary: array{url: ?string, folder: string}} $settings */
        $settings = $container->get('settings');
        $cloudinaryUrl = $settings['cloudinary']['url'];
        if (!is_string($cloudinaryUrl) || trim($cloudinaryUrl) === '') {
            throw new RuntimeException('CLOUDINARY_URL is not configured.');
        }

        return new CloudinaryService(
            new Cloudinary($cloudinaryUrl),
            $container->get(Clock::class),
            $settings['cloudinary']['folder'],
        );
    },
];
