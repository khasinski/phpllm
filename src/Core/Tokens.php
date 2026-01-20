<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Token usage information for a completion.
 */
final class Tokens
{
    public function __construct(
        public readonly int $input = 0,
        public readonly int $output = 0,
        public readonly int $cacheCreation = 0,
        public readonly int $cacheRead = 0,
    ) {
    }

    /**
     * Get total tokens used.
     */
    public function total(): int
    {
        return $this->input + $this->output;
    }

    /**
     * Create from provider-specific format.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            input: $data['input'] ?? $data['prompt_tokens'] ?? $data['input_tokens'] ?? 0,
            output: $data['output'] ?? $data['completion_tokens'] ?? $data['output_tokens'] ?? 0,
            cacheCreation: $data['cache_creation'] ?? $data['cache_creation_input_tokens'] ?? 0,
            cacheRead: $data['cache_read'] ?? $data['cache_read_input_tokens'] ?? 0,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'input' => $this->input,
            'output' => $this->output,
            'cache_creation' => $this->cacheCreation,
            'cache_read' => $this->cacheRead,
            'total' => $this->total(),
        ];
    }
}
