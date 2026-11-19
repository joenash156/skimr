<?php

declare(strict_types=1);

namespace App\Shared\Exception;

/**
 * Base for every domain exception that maps to an HTTP error (spec 10.1).
 *
 * Throw a subclass from any layer; ErrorHandler turns it into the
 * {"error": {"code", "message", "fields"}} envelope. The message is a
 * translation key, never user-facing text, so it can be shown in EN/FR/ES.
 */
abstract class HttpException extends \RuntimeException
{
    /**
     * @param array<string, string|int|float> $params placeholders for the translated message
     */
    public function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        private readonly string $messageKey,
        private readonly array $params = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageKey, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** Stable machine-readable code the frontend can switch on, e.g. QUOTA_EXCEEDED. */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    /** @return array<string, string|int|float> */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Field-level errors as translation keys, keyed by field name.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [];
    }

    /** @return array<string, string> extra response headers, e.g. Retry-After */
    public function headers(): array
    {
        return [];
    }
}
