<?php

declare(strict_types=1);

namespace PHPLLM\Contracts;

/**
 * Interface for tools that can be called by the LLM.
 */
interface ToolInterface
{
    /**
     * Get the tool name.
     */
    public function getName(): string;

    /**
     * Get the tool description.
     */
    public function getDescription(): string;

    /**
     * Get the parameter schema.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * Execute the tool with given arguments.
     *
     * @param array<string, mixed> $arguments
     * @return mixed
     */
    public function execute(array $arguments): mixed;

    /**
     * Convert tool to schema format for API requests.
     *
     * Returns a canonical schema that providers transform to their format:
     * - OpenAI/Ollama: Uses directly as function call format
     * - Anthropic: Transforms to {name, description, input_schema}
     * - Gemini: Transforms to {name, description, parameters}
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array;
}
