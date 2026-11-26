<?php

declare(strict_types=1);

use App\Public\Contact\ContactController;
use App\Public\Contact\ContactMessageRepository;
use App\Public\Contact\ContactMessageService;
use App\Public\Hero\HeroSlides;
use App\Public\Http\PublicErrorRenderer;
use App\Public\Seo\JsonLd;
use App\Public\Seo\MetaBuilder;
use App\Public\Seo\OgImageBuilder;
use App\Public\Seo\RobotsController;
use App\Public\Seo\SitemapController;
use App\Public\Site\SiteProfiles;
use App\Public\Twig\TransExtension;
use App\Shared\Clock\Clock;
use App\Shared\Http\JsonResponder;
use App\Shared\Mail\Mailer;
use App\Shared\Settings\SettingsRepository;
use App\Shared\Translator;
use Cloudinary\Cloudinary;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

use function DI\autowire;

return [
    Translator::class . '.public' => static fn (ContainerInterface $container): Translator => new Translator(
        $container->get('settings')['paths']['translations_public'],
    ),

    ContactController::class => static fn (ContainerInterface $container): ContactController => new ContactController(
        $container->get(ContactMessageService::class),
        $container->get(JsonResponder::class),
        $container->get(Translator::class . '.public'),
    ),

    ContactMessageService::class => static function (ContainerInterface $container): ContactMessageService {
        $settings = $container->get('settings');
        $recipient = $settings['mail']['reply_to'] ?? null;
        $teamInbox = is_string($recipient) && trim($recipient) !== '' ? trim($recipient) : null;

        return new ContactMessageService(
            $container->get(ContactMessageRepository::class),
            $container->get(Mailer::class),
            $container->get(Clock::class),
            $container->get(LoggerInterface::class),
            $teamInbox,
        );
    },

    ContactMessageRepository::class => autowire(),

    // Home-page carousel: admin-managed slides from settings, else the defaults.
    // Contact details + social links (footer, contact page): admin-managed in settings.
    SiteProfiles::class => static function (ContainerInterface $container): SiteProfiles {
        $settings = $container->get('settings');

        return new SiteProfiles(
            $container->get(SettingsRepository::class),
            $container->get(LoggerInterface::class),
            $settings['mail']['reply_to'] ?? null,
        );
    },

    HeroSlides::class => static fn (ContainerInterface $container): HeroSlides => new HeroSlides(
        $container->get(SettingsRepository::class),
        $container->get(Translator::class . '.public'),
        $container->get(LoggerInterface::class),
    ),

    MetaBuilder::class => static function (ContainerInterface $container): MetaBuilder {
        $settings = $container->get('settings');

        return new MetaBuilder(
            $container->get(Translator::class . '.public'),
            $settings['app']['url'],
            $container->get(OgImageBuilder::class),
        );
    },

    OgImageBuilder::class => static function (ContainerInterface $container): OgImageBuilder {
        $settings = $container->get('settings');
        $cloudinaryUrl = $settings['cloudinary']['url'];
        $cloudinary = is_string($cloudinaryUrl) && trim($cloudinaryUrl) !== ''
            ? new Cloudinary($cloudinaryUrl)
            : null;

        return new OgImageBuilder($settings['app']['url'], $settings['app']['env'], $cloudinary);
    },

    JsonLd::class => static function (ContainerInterface $container): JsonLd {
        $settings = $container->get('settings');

        return new JsonLd(
            $settings['app']['url'],
            $settings['app']['name'],
            $container->get(Translator::class . '.public'),
        );
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
        // Compiled templates are cached outside local development. auto_reload recompiles
        // a template when its file changes, so a deploy never serves stale pages.
        $local = in_array($settings['app']['env'], ['local', 'testing'], true);
        $twig = Twig::create($settings['paths']['templates'] . '/public', [
            'autoescape' => 'html',
            'cache' => $local ? false : $settings['paths']['var'] . '/cache/twig-public',
            'auto_reload' => true,
            'strict_variables' => true,
        ]);
        $twig->addExtension(new TransExtension($container->get(Translator::class . '.public')));
        $twig->getEnvironment()->addGlobal('site_url', $settings['app']['url']);
        // Templates call site.forLocale(locale); the settings row is read once, on first use.
        $twig->getEnvironment()->addGlobal('site', $container->get(SiteProfiles::class));
        $siteKey = $settings['turnstile']['site_key'] ?? null;
        $secretKey = $settings['turnstile']['secret_key'] ?? null;
        $siteKey = is_string($siteKey) && trim($siteKey) !== ''
            && is_string($secretKey) && trim($secretKey) !== ''
                ? trim($siteKey)
                : '';
        $twig->getEnvironment()->addGlobal(
            'turnstile_site_key',
            $siteKey,
        );

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
