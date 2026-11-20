<?php

declare(strict_types=1);

use App\Auth\AuthLinks;
use App\Auth\Console\CreateSuperAdminCommand;
use App\Auth\Google\GoogleAuthService;
use App\Auth\Google\GoogleClient;
use App\Auth\Google\LeagueGoogleClient;
use App\Auth\Google\SignedCookie;
use App\Auth\Http\AuthController;
use App\Auth\Http\CsrfGuard;
use App\Auth\Http\GoogleController;
use App\Auth\Jwt\JwtService;
use App\Auth\Password\PasswordHasher;
use App\Auth\Password\PasswordPolicy;
use App\Auth\RefreshCookie;
use App\Security\Audit\AuditLogger;
use App\Security\Turnstile\TurnstileVerifier;
use App\Shared\Clock\Clock;
use App\Shared\Http\DevApiDocsController;
use App\Shared\Http\JsonResponder;
use App\Shared\Mail\LogMailer;
use App\Shared\Mail\Mailer;
use App\Shared\Migrations\MigrationPageController;
use App\Shared\Migrations\MigrationRunner;
use App\Users\UserPresenter;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function DI\add;
use function DI\autowire;
use function DI\factory;

/*
 * Auth and security services (P1). Everything not listed here is autowired:
 * UserRepository, UserTokenService, AuditLogger, RateLimiter, RolePolicy,
 * AuthMiddleware, RateLimitMiddleware.
 */

/**
 * @return array{
 *     app: array{env: string, url: string},
 *     jwt: array{access_secret: ?string, refresh_secret: ?string, access_ttl: int, refresh_ttl: int},
 *     auth: array{bcrypt_cost: int, common_passwords: string, mail_log: string},
 *     turnstile: array{secret_key: ?string},
 *     google: array{client_id: ?string, client_secret: ?string, redirect_uri: ?string}
 * }
 */
$settings = static function (ContainerInterface $c): array {
    /** @var array{app: array{env: string, url: string}, jwt: array{access_secret: ?string, refresh_secret: ?string, access_ttl: int, refresh_ttl: int}, auth: array{bcrypt_cost: int, common_passwords: string, mail_log: string}, turnstile: array{secret_key: ?string}, google: array{client_id: ?string, client_secret: ?string, redirect_uri: ?string}} $s */
    $s = $c->get('settings');

    return $s;
};

/** The host part of APP_URL: the JWT issuer and the hostname Turnstile must report. */
$appHost = static fn (array $s): string => (string) (parse_url($s['app']['url'], PHP_URL_HOST) ?: 'localhost');

return [
    JwtService::class => static function (ContainerInterface $c) use ($settings, $appHost): JwtService {
        $s = $settings($c);

        return new JwtService(
            $s['jwt']['access_secret'],
            $s['jwt']['refresh_secret'],
            $appHost($s),
            $c->get(Clock::class),
            $s['jwt']['access_ttl'],
            $s['jwt']['refresh_ttl'],
        );
    },

    PasswordHasher::class => static fn (ContainerInterface $c): PasswordHasher => new PasswordHasher(
        $settings($c)['auth']['bcrypt_cost'],
    ),

    PasswordPolicy::class => static fn (ContainerInterface $c): PasswordPolicy => new PasswordPolicy(
        $settings($c)['auth']['common_passwords'],
    ),

    TurnstileVerifier::class => static function (ContainerInterface $c) use ($settings, $appHost): TurnstileVerifier {
        $s = $settings($c);

        return new TurnstileVerifier(
            new Client(),
            $s['turnstile']['secret_key'],
            $appHost($s),
            $s['app']['env'],
            $c->get(LoggerInterface::class),
        );
    },

    AuthLinks::class => static fn (ContainerInterface $c): AuthLinks => new AuthLinks($settings($c)['app']['url']),

    CsrfGuard::class => static fn (ContainerInterface $c): CsrfGuard => new CsrfGuard($settings($c)['app']['url']),

    AuthController::class => autowire()->constructorParameter(
        'accessTtl',
        factory(static fn (ContainerInterface $c): int => $settings($c)['jwt']['access_ttl']),
    ),

    'console.commands' => add([CreateSuperAdminCommand::class]),

    // Google sign-in (B1 task 12). Its two short-lived cookies are signed with a key
    // derived from JWT_REFRESH_SECRET, so .env needs no extra secret.
    GoogleClient::class => static fn (ContainerInterface $c): GoogleClient => new LeagueGoogleClient(
        $settings($c)['google']['client_id'],
        $settings($c)['google']['client_secret'],
        $settings($c)['google']['redirect_uri'],
        $c->get(Clock::class),
    ),

    'google.cookie_key' => static fn (ContainerInterface $c): string => hash_hmac(
        'sha256',
        'skimr google oauth cookies',
        (string) $settings($c)['jwt']['refresh_secret'],
    ),

    GoogleController::class => static fn (ContainerInterface $c): GoogleController => new GoogleController(
        $c->get(GoogleClient::class),
        $c->get(GoogleAuthService::class),
        new SignedCookie('__Secure-gstate', $c->get('google.cookie_key'), $c->get(Clock::class)),
        new SignedCookie('__Secure-glink', $c->get('google.cookie_key'), $c->get(Clock::class)),
        $c->get(RefreshCookie::class),
        $c->get(CsrfGuard::class),
        $c->get(UserPresenter::class),
        $c->get(JsonResponder::class),
        $c->get(LoggerInterface::class),
        $settings($c)['app']['url'],
        $settings($c)['jwt']['access_ttl'],
    ),

    // Local-only Swagger UI for the contract.
    DevApiDocsController::class => static fn (ContainerInterface $c): DevApiDocsController => new DevApiDocsController(
        $settings($c)['app']['env'],
        dirname(__DIR__, 3) . '/docs/api/openapi.yaml',
    ),

    // Browser migration page for staging (B4). The token comes straight from .env.
    MigrationRunner::class => static fn (ContainerInterface $c): MigrationRunner => new MigrationRunner(
        $c->get(Connection::class),
        dirname(__DIR__, 2) . '/migrations.php',
    ),

    MigrationPageController::class => static function (ContainerInterface $c): MigrationPageController {
        /** @var array{deploy: array{migration_token: ?string}} $s */
        $s = $c->get('settings');

        return new MigrationPageController(
            $c->get(MigrationRunner::class),
            $c->get(AuditLogger::class),
            $c->get(LoggerInterface::class),
            $s['deploy']['migration_token'],
        );
    },

    // P2 replaces this with MailService in config/container/mail.php (loaded after this file).
    Mailer::class => static fn (ContainerInterface $c): Mailer => new LogMailer(
        $settings($c)['auth']['mail_log'],
        $settings($c)['app']['env'],
        $c->get(Clock::class),
        $c->get(LoggerInterface::class),
    ),
];
