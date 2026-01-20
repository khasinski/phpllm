<?php

declare(strict_types=1);

namespace PHPLLM\Contracts;

use Generator;
use PHPLLM\Core\Message;

/**
 * Interface for LLM providers.
 *
 * All providers (OpenAI, Anthropic, Gemini, etc.) must implement this interface.
 */
interface ProviderInterface
{
    /**
     * Get the provider's slug identifier.
     */
    public function getSlug(): string;

    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Get the API base URL for this provider.
     */
    public function getApiBase(): string;

    /**
     * Get default headers for API requests.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Complete a chat conversation.
     *
     * @param array<Message> $messages
     * @param array<string, mixed> $options
     */
    public function complete(array $messages, array $options = []): Message;

    /**
     * Stream a chat completion.
     *
     * @param array<Message> $messages
     * @param array<string, mixed> $options
     * @return Generator<\PHPLLM\Core\Chunk>
     */
    public function stream(array $messages, array $options = []): Generator;

    /**
     * List available models for this provider.
     *
     * @return array<string, array<string, mixed>>
     */
    public function listModels(): array;

    /**
     * Check if the provider supports a specific capability.
     */
    public function supports(string $capability): bool;
}
