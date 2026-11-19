<?php

declare(strict_types=1);

use App\Security\Middleware\SecurityHeadersMiddleware;
use App\Shared\Clock\Clock;
use App\Shared\Clock\SystemClock;
use App\Shared\Db;
use App\Shared\Http\ErrorHandler;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\SpaController;
use App\Shared\Translator;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

use function DI\autowire;

/*
 * Builds the DI container: core definitions (P1) first, then every module
 * file in config/container/*.php. Module owners add their services in their
 * own file (projects.php, sources.php, public.php, ...) and never edit this one.
 * Each module file returns an array of PHP-DI definitions.
 *
 * @param array<string, mixed> $settings
 */
return static function (array $settings): ContainerInterface {
    $builder = new ContainerBuilder();
    $builder->useAutowiring(true);

    $builder->addDefinitions([
        'settings' => $settings,

        ResponseFactoryInterface::class => autowire(ResponseFactory::class),

        Clock::class => autowire(SystemClock::class),

        LoggerInterface::class => static function () use ($settings): LoggerInterface {
            $logger = new Logger('skimr');
            $logger->pushProcessor(new PsrLogMessageProcessor());
            $logger->pushHandler(new StreamHandler(
                $settings['paths']['var'] . '/log/app.log',
                Level::fromName(ucfirst((string) $settings['log']['level'])),
            ));

            return $logger;
        },

        Connection::class => static fn (): Connection => Db::connect($settings['db']),

        Translator::class => static fn (): Translator => new Translator($settings['paths']['translations_api']),

        JsonResponder::class => autowire(),

        ErrorHandler::class => static fn (ContainerInterface $c): ErrorHandler => new ErrorHandler(
            $c->get(ResponseFactoryInterface::class),
            $c->get(JsonResponder::class),
            $c->get(Translator::class),
            $c->get(LoggerInterface::class),
            // P5 can set this key in config/container/public.php to render HTML error pages for public URLs.
            $c->has('error.html_renderer') ? $c->get('error.html_renderer') : null,
        ),

        SecurityHeadersMiddleware::class => static fn (): SecurityHeadersMiddleware => new SecurityHeadersMiddleware(
            (string) $settings['app']['env'],
        ),

        SpaController::class => static fn (ContainerInterface $c): SpaController => new SpaController(
            $settings['paths']['spa_index'],
            $c->get(JsonResponder::class),
        ),

    ]);

    $moduleFiles = glob(__DIR__ . '/container/*.php') ?: [];
    sort($moduleFiles);
    foreach ($moduleFiles as $file) {
        $definitions = require $file;
        if (!is_array($definitions)) {
            throw new RuntimeException(sprintf('%s must return an array of container definitions.', $file));
        }
        $builder->addDefinitions($definitions);
    }

    return $builder->build();
};
