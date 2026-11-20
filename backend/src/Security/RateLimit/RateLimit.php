<?php

declare(strict_types=1);

namespace App\Security\RateLimit;

/**
 * A named rate-limit bucket (spec 11.4). Use the named constructors so every
 * module counts against the same limits:
 *
 *     $rateLimiter->consume(RateLimit::contact(), 'ip:' . $ip);
 */
final class RateLimit
{
    private function __construct(
        public readonly string $name,
        public readonly int $limit,
        public readonly int $windowSeconds,
    ) {
    }

    /** Login: 5 per 15 minutes per email + IP. */
    public static function login(): self
    {
        return new self('login', 5, 900);
    }

    /** Register: 5 per hour per IP. */
    public static function register(): self
    {
        return new self('register', 5, 3600);
    }

    /** Forgot password: 3 per hour per email. */
    public static function forgotPassword(): self
    {
        return new self('forgot_password', 3, 3600);
    }

    /** Resend verification email: 3 per hour per user. */
    public static function resendVerification(): self
    {
        return new self('resend_verification', 3, 3600);
    }

    /** Refresh: 30 per minute per user. */
    public static function refresh(): self
    {
        return new self('refresh', 30, 60);
    }

    /** Upload: 20 per hour per user. */
    public static function upload(): self
    {
        return new self('upload', 20, 3600);
    }

    /** Contact form: 3 per hour per IP. */
    public static function contact(): self
    {
        return new self('contact', 3, 3600);
    }

    /** Any other API call: 120 per minute per user (applied globally by RateLimitMiddleware). */
    public static function api(): self
    {
        return new self('api', 120, 60);
    }

    /** Add link: 30 per day per user. */
    public static function addLink(): self
    {
        return new self('add_link', 30, 86400);
    }
}
