<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

final class AiServiceException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        string $method,
        string $path,
    ) {
        parent::__construct(sprintf('AI service returned HTTP %d for %s %s.', $httpStatus, $method, $path));
    }
}
