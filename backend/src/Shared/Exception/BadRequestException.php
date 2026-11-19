<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class BadRequestException extends HttpException
{
    /**
     * @param array<string, string|int|float> $params
     */
    public function __construct(string $messageKey = 'errors.bad_request', array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct(400, 'BAD_REQUEST', $messageKey, $params, $previous);
    }
}
