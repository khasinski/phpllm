<?php

declare(strict_types=1);

namespace PHPLLM\Exceptions;

use PHPLLM\Core\ToolCall;
use Throwable;

/**
 * Exception thrown when a tool execution fails.
 *
 * This exception propagates tool errors rather than hiding them in the response,
 * allowing proper error handling by the calling code.
 */
class ToolExecutionException extends PHPLLMException
{
    public function __construct(
        string $message,
        public readonly string $toolName,
        public readonly ?ToolCall $toolCall = null,
        public readonly mixed $arguments = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create from a caught exception during tool execution.
     */
    public static function fromException(
        Throwable $e,
        ToolCall $toolCall,
    ): self {
        return new self(
            message: "Tool '{$toolCall->name}' failed: {$e->getMessage()}",
            toolName: $toolCall->name,
            toolCall: $toolCall,
            arguments: $toolCall->arguments,
            code: $e->getCode(),
            previous: $e,
        );
    }

    /**
     * Create for an unknown tool.
     */
    public static function unknownTool(ToolCall $toolCall, array $availableTools = []): self
    {
        $available = empty($availableTools)
            ? ''
            : ' Available tools: ' . implode(', ', $availableTools);

        return new self(
            message: "Unknown tool: '{$toolCall->name}'.{$available}",
            toolName: $toolCall->name,
            toolCall: $toolCall,
            arguments: $toolCall->arguments,
        );
    }
}
