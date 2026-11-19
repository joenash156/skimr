<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class ConflictException extends HttpException
{
    /**
     * @param array<string, string|int|float> $params
     */
    public function __construct(string $messageKey = 'errors.conflict', array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct(409, 'CONFLICT', $messageKey, $params, $previous);
    }
}
