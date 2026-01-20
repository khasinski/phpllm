<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Represents a tool/function call made by the LLM.
 */
final class ToolCall
{
    /**
     * @param array<string, mixed>|string $arguments Array for complete calls, string for streaming chunks
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array|string $arguments = [],
    ) {
    }

    /**
     * Create from provider-specific format.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? $data['function']['name'] ?? '',
            arguments: $data['arguments'] ?? $data['function']['arguments'] ?? [],
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
