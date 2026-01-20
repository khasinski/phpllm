<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Global configuration for PHPLLM.
 *
 * Holds API keys, default settings, and provider-specific configuration.
 */
final class Configuration
{
    private static ?self $instance = null;

    // Provider API keys
    private ?string $openaiApiKey = null;
    private ?string $anthropicApiKey = null;
    private ?string $geminiApiKey = null;
    private ?string $mistralApiKey = null;
    private ?string $deepseekApiKey = null;
    private ?string $xaiApiKey = null;
    private ?string $perplexityApiKey = null;

    // AWS Bedrock
    private ?string $bedrockAccessKeyId = null;
    private ?string $bedrockSecretAccessKey = null;
    private ?string $bedrockRegion = null;

    // Custom endpoints
    private ?string $openaiApiBase = null;
    private ?string $ollamaApiBase = null;

    // Default settings
    private ?string $defaultModel = null;
    private ?string $defaultProvider = null;
    private int $requestTimeout = 120;
    private int $maxRetries = 3;

    // Logging
    private bool $loggingEnabled = false;
    private mixed $logger = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Configure PHPLLM with an array of settings.
     *
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): self
    {
        $instance = self::getInstance();

        foreach ($config as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($instance, $method)) {
                $instance->$method($value);
            }
        }

        return $instance;
    }

    /**
     * Reset configuration to defaults (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // Setters with fluent interface

    public function setOpenaiApiKey(?string $key): self
    {
        $this->openaiApiKey = $key;
        return $this;
    }

    public function setAnthropicApiKey(?string $key): self
    {
        $this->anthropicApiKey = $key;
        return $this;
    }

    public function setGeminiApiKey(?string $key): self
    {
        $this->geminiApiKey = $key;
        return $this;
    }

    public function setMistralApiKey(?string $key): self
    {
        $this->mistralApiKey = $key;
        return $this;
    }

    public function setDeepseekApiKey(?string $key): self
    {
        $this->deepseekApiKey = $key;
        return $this;
    }

    public function setXaiApiKey(?string $key): self
    {
        $this->xaiApiKey = $key;
        return $this;
    }

    public function setPerplexityApiKey(?string $key): self
    {
        $this->perplexityApiKey = $key;
        return $this;
    }

    public function setBedrockAccessKeyId(?string $key): self
    {
        $this->bedrockAccessKeyId = $key;
        return $this;
    }

    public function setBedrockSecretAccessKey(?string $key): self
    {
        $this->bedrockSecretAccessKey = $key;
        return $this;
    }

    public function setBedrockRegion(?string $region): self
    {
        $this->bedrockRegion = $region;
        return $this;
    }

    public function setOpenaiApiBase(?string $base): self
    {
        $this->openaiApiBase = $base;
        return $this;
    }

    public function setOllamaApiBase(?string $base): self
    {
        $this->ollamaApiBase = $base;
        return $this;
    }

    public function setDefaultModel(?string $model): self
    {
        $this->defaultModel = $model;
        return $this;
    }

    public function setDefaultProvider(?string $provider): self
    {
        $this->defaultProvider = $provider;
        return $this;
    }

    public function setRequestTimeout(int $timeout): self
    {
        $this->requestTimeout = $timeout;
        return $this;
    }

    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $retries;
        return $this;
    }

    public function setLoggingEnabled(bool $enabled): self
    {
        $this->loggingEnabled = $enabled;
        return $this;
    }

    public function setLogger(mixed $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    // Getters

    public function getOpenaiApiKey(): ?string
    {
        return $this->openaiApiKey;
    }

    public function getAnthropicApiKey(): ?string
    {
        return $this->anthropicApiKey;
    }

    public function getGeminiApiKey(): ?string
    {
        return $this->geminiApiKey;
    }

    public function getMistralApiKey(): ?string
    {
        return $this->mistralApiKey;
    }

    public function getDeepseekApiKey(): ?string
    {
        return $this->deepseekApiKey;
    }

    public function getXaiApiKey(): ?string
    {
        return $this->xaiApiKey;
    }

    public function getPerplexityApiKey(): ?string
    {
        return $this->perplexityApiKey;
    }

    public function getBedrockAccessKeyId(): ?string
    {
        return $this->bedrockAccessKeyId;
    }

    public function getBedrockSecretAccessKey(): ?string
    {
        return $this->bedrockSecretAccessKey;
    }

    public function getBedrockRegion(): ?string
    {
        return $this->bedrockRegion;
    }

    public function getOpenaiApiBase(): string
    {
        return $this->openaiApiBase ?? 'https://api.openai.com/v1';
    }

    public function getOllamaApiBase(): string
    {
        return $this->ollamaApiBase ?? 'http://localhost:11434';
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel ?? 'gpt-4o-mini';
    }

    public function getDefaultProvider(): ?string
    {
        return $this->defaultProvider;
    }

    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function isLoggingEnabled(): bool
    {
        return $this->loggingEnabled;
    }

    public function getLogger(): mixed
    {
        return $this->logger;
    }

    /**
     * Get configuration as array (useful for debugging).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'openai_api_key' => $this->openaiApiKey ? '***' : null,
            'anthropic_api_key' => $this->anthropicApiKey ? '***' : null,
            'gemini_api_key' => $this->geminiApiKey ? '***' : null,
            'default_model' => $this->defaultModel,
            'default_provider' => $this->defaultProvider,
            'request_timeout' => $this->requestTimeout,
            'max_retries' => $this->maxRetries,
        ];
    }
}
