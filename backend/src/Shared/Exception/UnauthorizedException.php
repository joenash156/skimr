<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class UnauthorizedException extends HttpException
{
    /**
     * @param array<string, string|int|float> $params
     */
    public function __construct(string $messageKey = 'errors.unauthenticated', array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct(401, 'UNAUTHENTICATED', $messageKey, $params, $previous);
    }
}
