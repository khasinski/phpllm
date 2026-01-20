<?php

declare(strict_types=1);

namespace PHPLLM\Exceptions;

/**
 * Thrown when rate limit is exceeded (HTTP 429).
 */
class RateLimitException extends ApiException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        ?string $provider = null,
        ?array $response = null,
        protected ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $provider, $response, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
