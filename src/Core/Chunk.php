<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Represents a streaming chunk from an LLM response.
 */
final class Chunk
{
    /**
     * @param array<ToolCall> $toolCalls Partial tool calls being built
     */
    public function __construct(
        public readonly ?string $content = null,
        public readonly ?string $thinking = null,
        public readonly array $toolCalls = [],
        public readonly ?string $stopReason = null,
        public readonly ?Tokens $tokens = null,
        public readonly bool $isFirst = false,
        public readonly bool $isLast = false,
    ) {
    }

    /**
     * Check if chunk has content.
     */
    public function hasContent(): bool
    {
        return $this->content !== null && $this->content !== '';
    }

    /**
     * Check if chunk has thinking content.
     */
    public function hasThinking(): bool
    {
        return $this->thinking !== null && $this->thinking !== '';
    }

    /**
     * Check if this is the final chunk.
     */
    public function isDone(): bool
    {
        return $this->isLast || $this->stopReason !== null;
    }
}
