<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class NotFoundException extends HttpException
{
    /**
     * @param array<string, string|int|float> $params
     */
    public function __construct(string $messageKey = 'errors.not_found', array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct(404, 'NOT_FOUND', $messageKey, $params, $previous);
    }
}
