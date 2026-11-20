<?php

declare(strict_types=1);

/*
 * All configuration, read from the environment (.env) in one place.
 * Code never calls getenv() or reads $_ENV directly: it gets values from
 * the container key "settings". Defaults are safe for local development
 * only; staging and production set every value in their own .env.
 */

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && $value !== '' ? $value : $default;
};

$bool = static fn (string $key, bool $default): bool => filter_var(
    $env($key, $default ? 'true' : 'false'),
    FILTER_VALIDATE_BOOLEAN,
);

$appEnv = $env('APP_ENV', 'local') ?? 'local';

return [
    'app' => [
        'env' => $appEnv,
        // Never trust APP_DEBUG=true outside local/testing (spec 11.2, "Information leakage").
        'debug' => in_array($appEnv, ['local', 'testing'], true) && $bool('APP_DEBUG', false),
        'url' => rtrim($env('APP_URL', 'http://localhost:8080') ?? '', '/'),
        'name' => $env('APP_NAME', 'Skimr'),
        'slug' => 'skimr',
    ],
    'paths' => [
        'root' => dirname(__DIR__),
        'translations_api' => dirname(__DIR__) . '/translations/api',
        'translations_public' => dirname(__DIR__) . '/translations/public',
        'templates' => dirname(__DIR__) . '/templates',
        'var' => dirname(__DIR__) . '/var',
        'spa_index' => dirname(__DIR__) . '/public/app/index.html',
    ],
    'db' => [
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', 'skimr_dev'),
        'user' => $env('DB_USER', 'root'),
        'pass' => $env('DB_PASS', ''),
    ],
    'jwt' => [
        'access_secret' => $env('JWT_ACCESS_SECRET'),
        'refresh_secret' => $env('JWT_REFRESH_SECRET'),
        'access_ttl' => 900,
        'refresh_ttl' => 1209600,
    ],
    'auth' => [
        // Spec 11.2: bcrypt cost 12. Tests use the minimum (4) so the suite stays fast.
        'bcrypt_cost' => $appEnv === 'testing' ? 4 : 12,
        'common_passwords' => dirname(__DIR__) . '/resources/common-passwords.txt',
        // Local fallback for email until P2's MailService replaces the Mailer binding.
        'mail_log' => dirname(__DIR__) . '/var/mail.log',
    ],
    'google' => [
        'client_id' => $env('GOOGLE_CLIENT_ID'),
        'client_secret' => $env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => $env('GOOGLE_REDIRECT_URI'),
    ],
    'turnstile' => [
        'site_key' => $env('TURNSTILE_SITE_KEY'),
        'secret_key' => $env('TURNSTILE_SECRET_KEY'),
    ],
    'mail' => [
        'resend_api_key' => $env('RESEND_API_KEY'),
        'from' => $env('MAIL_FROM'),
        'reply_to' => $env('MAIL_REPLY_TO'),
    ],
    'cloudinary' => [
        'url' => $env('CLOUDINARY_URL'),
    ],
    'ai_service' => [
        'url' => rtrim($env('AI_SERVICE_URL', 'http://localhost:8000') ?? '', '/'),
        'secret' => $env('AI_SERVICE_SECRET'),
    ],
    'log' => [
        'level' => $env('LOG_LEVEL', $appEnv === 'production' ? 'warning' : 'debug'),
    ],
];
