<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * 429 for both quota limits (spec 6.6) and rate limits (spec 11.4).
 * Always carries Retry-After, as spec 10.1 requires.
 */
final class QuotaExceededException extends HttpException
{
    /**
     * @param array<string, string|int|float> $params
     */
    public function __construct(
        private readonly int $retryAfterSeconds = 60,
        string $errorCode = 'QUOTA_EXCEEDED',
        string $messageKey = 'errors.quota_exceeded',
        array $params = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(429, $errorCode, $messageKey, $params, $previous);
    }

    public static function rateLimited(int $retryAfterSeconds): self
    {
        return new self($retryAfterSeconds, 'RATE_LIMITED', 'errors.rate_limited');
    }

    public function retryAfter(): int
    {
        return $this->retryAfterSeconds;
    }

    public function headers(): array
    {
        return ['Retry-After' => (string) max(1, $this->retryAfterSeconds)];
    }
}
