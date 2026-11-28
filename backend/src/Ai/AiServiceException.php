<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/**
 * The AI service answered with an unexpected HTTP status. `errorCode` is the
 * service's machine-readable code (for example `unknown_job_type` or
 * `expired_timestamp`) when it sent one; the message never includes the body.
 */
final class AiServiceException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        string $method,
        string $path,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct(sprintf(
            'AI service returned HTTP %d%s for %s %s.',
            $httpStatus,
            $errorCode === null ? '' : ' (' . $errorCode . ')',
            $method,
            $path,
        ));
    }
}
