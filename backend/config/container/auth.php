<?php

declare(strict_types=1);

use App\Auth\AuthLinks;
use App\Auth\Console\CreateSuperAdminCommand;
use App\Auth\Http\AuthController;
use App\Auth\Http\CsrfGuard;
use App\Auth\Jwt\JwtService;
use App\Auth\Password\PasswordHasher;
use App\Auth\Password\PasswordPolicy;
use App\Security\Turnstile\TurnstileVerifier;
use App\Shared\Clock\Clock;
use App\Shared\Mail\LogMailer;
use App\Shared\Mail\Mailer;
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
 *     turnstile: array{secret_key: ?string}
 * }
 */
$settings = static function (ContainerInterface $c): array {
    /** @var array{app: array{env: string, url: string}, jwt: array{access_secret: ?string, refresh_secret: ?string, access_ttl: int, refresh_ttl: int}, auth: array{bcrypt_cost: int, common_passwords: string, mail_log: string}, turnstile: array{secret_key: ?string}} $s */
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

    // P2 replaces this with MailService in config/container/mail.php (loaded after this file).
    Mailer::class => static fn (ContainerInterface $c): Mailer => new LogMailer(
        $settings($c)['auth']['mail_log'],
        $settings($c)['app']['env'],
        $c->get(Clock::class),
        $c->get(LoggerInterface::class),
    ),
];
