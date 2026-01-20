<?php

declare(strict_types=1);

namespace PHPLLM\Exceptions;

use Throwable;

/**
 * Exception thrown when streaming operations fail.
 *
 * This includes connection failures during stream setup,
 * disconnections mid-stream, and read errors.
 */
class StreamException extends PHPLLMException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
