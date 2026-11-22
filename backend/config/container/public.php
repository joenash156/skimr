<?php

declare(strict_types=1);

use App\Public\Http\PublicErrorRenderer;
use App\Public\Seo\JsonLd;
use App\Public\Seo\MetaBuilder;
use App\Public\Seo\RobotsController;
use App\Public\Seo\SitemapController;
use App\Public\Twig\TransExtension;
use App\Shared\Translator;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

return [
    Translator::class . '.public' => static fn (ContainerInterface $container): Translator => new Translator(
        $container->get('settings')['paths']['translations_public'],
    ),

    MetaBuilder::class => static function (ContainerInterface $container): MetaBuilder {
        $settings = $container->get('settings');

        return new MetaBuilder(
            $container->get(Translator::class . '.public'),
            $settings['app']['url'],
        );
    },

    JsonLd::class => static function (ContainerInterface $container): JsonLd {
        $settings = $container->get('settings');

        return new JsonLd($settings['app']['url'], $settings['app']['name']);
    },

    RobotsController::class => static function (ContainerInterface $container): RobotsController {
        $settings = $container->get('settings');

        return new RobotsController($settings['app']['url']);
    },

    SitemapController::class => static function (ContainerInterface $container): SitemapController {
        $settings = $container->get('settings');

        return new SitemapController(
            $settings['app']['url'],
            $settings['paths']['var'] . '/seo',
            $settings['paths']['templates'] . '/public',
            $settings['paths']['translations_public'],
        );
    },

    Twig::class . '.public' => static function (ContainerInterface $container): Twig {
        $settings = $container->get('settings');
        $twig = Twig::create($settings['paths']['templates'] . '/public', [
            'autoescape' => 'html',
            'cache' => false,
            'strict_variables' => true,
        ]);
        $twig->addExtension(new TransExtension($container->get(Translator::class . '.public')));
        $twig->getEnvironment()->addGlobal('site_url', $settings['app']['url']);

        return $twig;
    },

    'error.html_renderer' => static function (ContainerInterface $container): Closure {
        $renderer = new PublicErrorRenderer(
            $container->get(Twig::class . '.public'),
            $container->get(\Psr\Http\Message\ResponseFactoryInterface::class),
        );

        return static fn ($request, int $status) => $renderer->render($request, $status);
    },
];
