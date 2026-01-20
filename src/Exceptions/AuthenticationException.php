<?php

declare(strict_types=1);

namespace PHPLLM\Exceptions;

/**
 * Thrown when authentication fails (HTTP 401/403).
 */
class AuthenticationException extends ApiException
{
    public function __construct(
        string $message = 'Authentication failed',
        int $statusCode = 401,
        ?string $provider = null,
        ?array $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $provider, $response, $previous);
    }
}
