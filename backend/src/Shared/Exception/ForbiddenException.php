<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class ForbiddenException extends HttpException
{
    /**
     * @param array<string, string|int|float> $params
     */
    public function __construct(string $messageKey = 'errors.forbidden', array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct(403, 'FORBIDDEN', $messageKey, $params, $previous);
    }
}
