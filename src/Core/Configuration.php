<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use PHPLLM\Exceptions\ConfigurationException;
use Psr\Log\LoggerInterface;

/**
 * Configuration for PHPLLM.
 *
 * Can be used as singleton (getInstance) or instantiated directly for DI.
 *
 * Example (singleton):
 * ```php
 * PHPLLM::configure(['openai_api_key' => 'sk-...']);
 * ```
 *
 * Example (DI):
 * ```php
 * $config = new Configuration(['openai_api_key' => 'sk-...']);
 * $provider = new OpenAIProvider($config);
 * ```
 */
final class Configuration
{
    private static ?self $instance = null;

    /** @var array<string> Valid configuration keys */
    private const VALID_KEYS = [
        'openai_api_key',
        'anthropic_api_key',
        'gemini_api_key',
        'mistral_api_key',
        'deepseek_api_key',
        'xai_api_key',
        'perplexity_api_key',
        'bedrock_access_key_id',
        'bedrock_secret_access_key',
        'bedrock_region',
        'openai_api_base',
        'ollama_api_base',
        'default_model',
        'default_provider',
        'request_timeout',
        'max_retries',
        'logging_enabled',
        'logger',
        'model_aliases',
        'default_temperature',
        'default_max_tokens',
    ];

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
    private ?LoggerInterface $logger = null;

    // Model aliases
    /** @var array<string, string> */
    private array $modelAliases = [
        'fast' => 'gpt-4o-mini',
        'smart' => 'gpt-5.2',
        'cheap' => 'gpt-4o-mini',
        'claude' => 'claude-sonnet-4-5-20250929',
        'opus' => 'claude-opus-4-5-20251101',
        'sonnet' => 'claude-sonnet-4-5-20250929',
        'haiku' => 'claude-haiku-4-5-20251101',
        'gemini' => 'gemini-2.0-flash',
        'local' => 'llama3.2',
    ];

    // Default chat settings
    private ?float $defaultTemperature = null;
    private ?int $defaultMaxTokens = null;

    /**
     * Create a new Configuration instance.
     *
     * @param array<string, mixed> $config Configuration options
     *
     * @throws ConfigurationException If unknown configuration keys are provided
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->applyConfig($config);
        }
    }

    /**
     * Get the global singleton instance.
     *
     * For DI usage, prefer `new Configuration()` instead.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Configure the global singleton with an array of settings.
     *
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException If unknown configuration keys are provided
     */
    public static function configure(array $config): self
    {
        $instance = self::getInstance();
        $instance->applyConfig($config);

        return $instance;
    }

    /**
     * Apply configuration array to this instance.
     *
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException If unknown configuration keys are provided
     */
    private function applyConfig(array $config): void
    {
        // Validate all keys first
        $unknownKeys = array_diff(array_keys($config), self::VALID_KEYS);
        if (!empty($unknownKeys)) {
            throw new ConfigurationException(
                'Unknown configuration keys: ' . implode(', ', $unknownKeys) .
                '. Valid keys are: ' . implode(', ', self::VALID_KEYS)
            );
        }

        // Apply configuration
        foreach ($config as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        // Wire up logger when both are set
        if ($this->loggingEnabled && $this->logger !== null) {
            Logger::setLogger($this->logger);
        } elseif (!$this->loggingEnabled) {
            Logger::setLogger(null);
        }
    }

    /**
     * Reset global singleton to defaults (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
        Logger::setLogger(null);
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
        if ($timeout < 1) {
            throw new \InvalidArgumentException('Request timeout must be at least 1 second');
        }
        if ($timeout > 600) {
            throw new \InvalidArgumentException('Request timeout cannot exceed 600 seconds');
        }
        $this->requestTimeout = $timeout;
        return $this;
    }

    public function setMaxRetries(int $retries): self
    {
        if ($retries < 0) {
            throw new \InvalidArgumentException('Max retries cannot be negative');
        }
        if ($retries > 10) {
            throw new \InvalidArgumentException('Max retries cannot exceed 10');
        }
        $this->maxRetries = $retries;
        return $this;
    }

    public function setLoggingEnabled(bool $enabled): self
    {
        $this->loggingEnabled = $enabled;

        // Auto-wire logger when enabled
        if ($enabled && $this->logger !== null) {
            Logger::setLogger($this->logger);
        } elseif (!$enabled) {
            Logger::setLogger(null);
        }

        return $this;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;

        // Auto-wire logger when set
        if ($this->loggingEnabled && $logger !== null) {
            Logger::setLogger($logger);
        }

        return $this;
    }

    /**
     * Set model aliases.
     *
     * @param array<string, string> $aliases
     */
    public function setModelAliases(array $aliases): self
    {
        $this->modelAliases = array_merge($this->modelAliases, $aliases);
        return $this;
    }

    /**
     * Add a single model alias.
     */
    public function addModelAlias(string $alias, string $model): self
    {
        $this->modelAliases[$alias] = $model;
        return $this;
    }

    public function setDefaultTemperature(?float $temperature): self
    {
        if ($temperature !== null && ($temperature < 0.0 || $temperature > 2.0)) {
            throw new \InvalidArgumentException('Temperature must be between 0.0 and 2.0');
        }
        $this->defaultTemperature = $temperature;
        return $this;
    }

    public function setDefaultMaxTokens(?int $maxTokens): self
    {
        if ($maxTokens !== null && $maxTokens < 1) {
            throw new \InvalidArgumentException('Max tokens must be at least 1');
        }
        $this->defaultMaxTokens = $maxTokens;
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

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get all model aliases.
     *
     * @return array<string, string>
     */
    public function getModelAliases(): array
    {
        return $this->modelAliases;
    }

    /**
     * Resolve a model alias to its full model name.
     * Returns the original model if no alias exists.
     */
    public function resolveModelAlias(string $model): string
    {
        return $this->modelAliases[$model] ?? $model;
    }

    public function getDefaultTemperature(): ?float
    {
        return $this->defaultTemperature;
    }

    public function getDefaultMaxTokens(): ?int
    {
        return $this->defaultMaxTokens;
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
