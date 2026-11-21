<?php

namespace KusikusiCMS\Api\Support;

use RuntimeException;

/**
 * An error with a stable machine code, rendered as the API error envelope {message, code, errors?}.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Invalid credentials on login (422 invalid_credentials).
     */
    public static function invalidCredentials(): self
    {
        return new self('The provided credentials are incorrect.', 'invalid_credentials', 422);
    }
}
