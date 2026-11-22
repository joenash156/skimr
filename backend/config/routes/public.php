<?php

declare(strict_types=1);

use App\Public\Contact\ContactController;
use App\Public\Http\LocaleMiddleware;
use App\Public\Http\PageController;
use App\Public\Http\PublicController;
use App\Public\Http\TrailingSlashMiddleware;
use App\Public\Seo\PublicPageMap;
use App\Public\Seo\RobotsController;
use App\Public\Seo\SitemapController;
use App\Security\RateLimit\RateLimit;
use App\Security\RateLimit\RateLimiter;
use App\Security\Turnstile\TurnstileVerifier;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

/*
 * Public server-rendered pages (P5). Public URLs never use the React shell.
 *
 * @param App<ContainerInterface|null> $app
 */
return static function (App $app): void {
    // This context middleware must wrap the error handler so localized 404s retain the URL locale.
    $app->add(LocaleMiddleware::class);
    $app->get('/', [PublicController::class, 'root']);
    $app->get('/{lang:(?:en|fr|es)}', [PublicController::class, 'languageHomeRedirect']);
    $app->get('/robots.txt', [RobotsController::class, 'show']);
    $app->get('/sitemap.xml', [SitemapController::class, 'index']);
    $app->get('/sitemap-pages.xml', [SitemapController::class, 'pages']);

    $container = $app->getContainer() ?? throw new RuntimeException('The app must be built with a container.');
    /** @var TurnstileVerifier $turnstile */
    $turnstile = $container->get(TurnstileVerifier::class);
    /** @var RateLimiter $limiter */
    $limiter = $container->get(RateLimiter::class);
    $app->post('/api/v1/contact', [ContactController::class, 'send'])
        ->add($turnstile->middleware('contact'))
        ->add($limiter->middleware(RateLimit::contact(), RateLimiter::byIp()));

    /** @var Twig $twig */
    $twig = $container->get(Twig::class . '.public');
    $twigMiddleware = TwigMiddleware::create($app, $twig);

    // Not static: Slim binds group callbacks to the container.
    $public = $app->group('/{lang:(?:en|fr|es)}', function (RouteCollectorProxy $group): void {
        $group->get('/', [PublicController::class, 'home']);
        $group->get('/{page:' . PublicPageMap::routePattern() . '}', [PageController::class, 'show']);
        $group->get('/{path:.*}', [PublicController::class, 'notFound']);
    });

    $public->add(TrailingSlashMiddleware::class);
    $public->add($twigMiddleware);
};
