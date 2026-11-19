<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class PayloadTooLargeException extends HttpException
{
    /**
     * @param array<string, string|int|float> $params
     */
    public function __construct(string $messageKey = 'errors.payload_too_large', array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct(413, 'PAYLOAD_TOO_LARGE', $messageKey, $params, $previous);
    }
}
