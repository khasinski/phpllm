<?php

declare(strict_types=1);

namespace PHPLLM\Providers;

use Generator;
use PHPLLM\Contracts\ProviderInterface;
use PHPLLM\Core\Chunk;
use PHPLLM\Core\Configuration;
use PHPLLM\Core\Connection;
use PHPLLM\Core\Message;
use PHPLLM\Exceptions\ConfigurationException;

/**
 * Base class for LLM providers.
 *
 * Provides common functionality for API communication.
 */
abstract class BaseProvider implements ProviderInterface
{
    protected Configuration $config;
    protected Connection $connection;

    /** @var array<string> Supported capabilities */
    protected array $capabilities = ['chat'];

    public function __construct(?Configuration $config = null, ?Connection $connection = null)
    {
        $this->config = $config ?? Configuration::getInstance();
        $this->connection = $connection ?? new Connection($this->config);
    }

    /**
     * Get the API key for this provider.
     */
    abstract protected function getApiKey(): ?string;

    /**
     * Render messages into provider-specific format.
     *
     * @param array<Message> $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    abstract protected function renderPayload(array $messages, array $options): array;

    /**
     * Parse completion response into a Message.
     *
     * @param array<string, mixed> $response
     */
    abstract protected function parseCompletion(array $response): Message;

    /**
     * Parse a streaming chunk.
     *
     * @param array<string, mixed> $data
     */
    abstract protected function parseStreamChunk(array $data): Chunk;

    public function isConfigured(): bool
    {
        return $this->getApiKey() !== null;
    }

    public function getHeaders(): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === null) {
            throw ConfigurationException::missingApiKey($this->getSlug());
        }

        return [
            'Authorization' => "Bearer {$apiKey}",
        ];
    }

    public function complete(array $messages, array $options = []): Message
    {
        $url = $this->getApiBase() . $this->getCompletionEndpoint();
        $payload = $this->renderPayload($messages, $options);

        $response = $this->connection->post($url, $this->getHeaders(), $payload);

        return $this->parseCompletion($response);
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $url = $this->getApiBase() . $this->getCompletionEndpoint();
        $payload = $this->renderPayload($messages, $options);

        // Most providers (OpenAI, Anthropic) require stream: true in payload
        $payload['stream'] = true;

        foreach ($this->connection->stream($url, $this->getHeaders(), $payload) as $data) {
            $decoded = json_decode($data, true);
            if ($decoded !== null) {
                yield $this->parseStreamChunk($decoded);
            }
        }
    }

    public function listModels(): array
    {
        return [];
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * Get the completion endpoint path.
     */
    protected function getCompletionEndpoint(): string
    {
        return '/chat/completions';
    }
}
