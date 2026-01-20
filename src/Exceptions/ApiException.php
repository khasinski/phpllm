<?php

declare(strict_types=1);

namespace PHPLLM\Exceptions;

/**
 * Base class for API-related errors.
 */
class ApiException extends PHPLLMException
{
    public function __construct(
        string $message,
        protected int $statusCode = 0,
        protected ?string $provider = null,
        protected ?array $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}
