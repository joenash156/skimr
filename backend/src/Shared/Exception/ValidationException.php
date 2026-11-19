<?php

declare(strict_types=1);

namespace App\Shared\Exception;

final class ValidationException extends HttpException
{
    /**
     * @param array<string, string> $fieldErrors field name => translation key, e.g. ['email' => 'validation.email.taken']
     * @param array<string, string|int|float> $params
     */
    public function __construct(
        private readonly array $fieldErrors = [],
        string $messageKey = 'errors.validation',
        array $params = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(422, 'VALIDATION_FAILED', $messageKey, $params, $previous);
    }

    public function fields(): array
    {
        return $this->fieldErrors;
    }
}
