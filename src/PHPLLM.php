<?php

declare(strict_types=1);

namespace PHPLLM;

use PHPLLM\Contracts\EmbeddingInterface;
use PHPLLM\Contracts\ImageGenerationInterface;
use PHPLLM\Contracts\ProviderInterface;
use PHPLLM\Core\Chat;
use PHPLLM\Core\Configuration;
use PHPLLM\Core\Embedding;
use PHPLLM\Core\Image;
use PHPLLM\Exceptions\ConfigurationException;
use PHPLLM\Providers\Anthropic\AnthropicProvider;
use PHPLLM\Providers\Gemini\GeminiProvider;
use PHPLLM\Providers\Ollama\OllamaProvider;
use PHPLLM\Providers\OpenAI\OpenAIProvider;

/**
 * Main facade for PHPLLM.
 *
 * Provides a simple, elegant API for interacting with LLMs.
 *
 * Example:
 * ```php
 * // Configure
 * PHPLLM::configure([
 *     'openai_api_key' => 'sk-...',
 * ]);
 *
 * // Chat
 * $chat = PHPLLM::chat();
 * $response = $chat->ask('What is PHP?');
 *
 * // Embeddings
 * $embedding = PHPLLM::embed('Hello world');
 *
 * // Image generation
 * $image = PHPLLM::paint('A sunset over mountains');
 * ```
 */
final class PHPLLM
{
    /** @var array<string, class-string<ProviderInterface>> */
    private static array $providers = [
        'openai' => OpenAIProvider::class,
        'anthropic' => AnthropicProvider::class,
        'gemini' => GeminiProvider::class,
        'ollama' => OllamaProvider::class,
    ];

    /** @var array<string, string> Model to provider mapping */
    private static array $modelProviders = [
        // OpenAI GPT-5.2 Series (Latest - December 2025)
        'gpt-5.2' => 'openai',
        'gpt-5.2-pro' => 'openai',
        'gpt-5.2-chat-latest' => 'openai',
        'gpt-5.2-codex' => 'openai',

        // OpenAI GPT-5.1 Series
        'gpt-5.1' => 'openai',

        // OpenAI GPT-5 (Original)
        'gpt-5' => 'openai',

        // OpenAI GPT-4.1 Series
        'gpt-4.1' => 'openai',
        'gpt-4.1-mini' => 'openai',
        'gpt-4.1-nano' => 'openai',

        // OpenAI Reasoning Models (o-series)
        'o3' => 'openai',
        'o3-mini' => 'openai',
        'o3-pro' => 'openai',
        'o4-mini' => 'openai',

        // OpenAI GPT-4o Series (Legacy)
        'gpt-4o' => 'openai',
        'gpt-4o-mini' => 'openai',
        'gpt-4o-audio-preview' => 'openai',

        // OpenAI Embeddings
        'text-embedding-3-small' => 'openai',
        'text-embedding-3-large' => 'openai',
        'text-embedding-ada-002' => 'openai',

        // OpenAI Images
        'gpt-image-1.5' => 'openai',
        'dall-e-3' => 'openai',
        'dall-e-2' => 'openai',

        // Anthropic Claude 4.5 (Latest)
        'claude-opus-4-5-20251101' => 'anthropic',
        'claude-sonnet-4-5-20250929' => 'anthropic',
        'claude-haiku-4-5-20251101' => 'anthropic',

        // Anthropic Claude 4
        'claude-opus-4-20250514' => 'anthropic',
        'claude-sonnet-4-20250514' => 'anthropic',

        // Anthropic Claude 3.5 (Legacy)
        'claude-3-5-sonnet-20241022' => 'anthropic',
        'claude-3-5-haiku-20241022' => 'anthropic',
        'claude-3-opus-20240229' => 'anthropic',

        // Gemini
        'gemini-2.0-flash' => 'gemini',
        'gemini-2.0-flash-lite' => 'gemini',
        'gemini-2.0-pro' => 'gemini',
        'gemini-1.5-pro' => 'gemini',
        'gemini-1.5-flash' => 'gemini',
        'gemini-1.5-flash-8b' => 'gemini',

        // Ollama (local models)
        'llama3.2' => 'ollama',
        'llama3.2:1b' => 'ollama',
        'llama3.1' => 'ollama',
        'llama3.1:70b' => 'ollama',
        'codellama' => 'ollama',
        'deepseek-coder-v2' => 'ollama',
        'mistral' => 'ollama',
        'mixtral' => 'ollama',
        'phi3' => 'ollama',
        'gemma2' => 'ollama',
        'qwen2.5' => 'ollama',
    ];

    /**
     * Configure PHPLLM.
     *
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): Configuration
    {
        return Configuration::configure($config);
    }

    /**
     * Get the configuration instance.
     */
    public static function config(): Configuration
    {
        return Configuration::getInstance();
    }

    /**
     * Create a new chat instance.
     *
     * @param string|null $model Model ID, alias, or null for default
     * @param string|null $provider Provider slug or null to auto-detect
     */
    public static function chat(?string $model = null, ?string $provider = null): Chat
    {
        $config = Configuration::getInstance();

        $model ??= $config->getDefaultModel();
        $model = $config->resolveModelAlias($model);
        $provider ??= self::detectProvider($model);

        $providerInstance = self::getProvider($provider);

        $chat = new Chat($providerInstance, $model);

        // Apply default settings if configured
        if ($config->getDefaultTemperature() !== null) {
            $chat->withTemperature($config->getDefaultTemperature());
        }
        if ($config->getDefaultMaxTokens() !== null) {
            $chat->withMaxTokens($config->getDefaultMaxTokens());
        }

        return $chat;
    }

    /**
     * Generate embeddings for text.
     *
     * @param string|array<string> $input Text or array of texts to embed
     * @param string|null $model Embedding model (default: text-embedding-3-small)
     * @param array<string, mixed> $options Additional options
     * @return Embedding|array<Embedding>
     */
    public static function embed(
        string|array $input,
        ?string $model = null,
        array $options = [],
    ): Embedding|array {
        $model ??= 'text-embedding-3-small';
        $provider = self::detectProvider($model);
        $providerInstance = self::getProvider($provider);

        if (!$providerInstance instanceof EmbeddingInterface) {
            throw new ConfigurationException("Provider '{$provider}' does not support embeddings");
        }

        $options['model'] = $model;
        return $providerInstance->embed($input, $options);
    }

    /**
     * Generate an image from a prompt.
     *
     * @param string $prompt Description of the image to generate
     * @param string|null $model Image model (default: gpt-image-1.5)
     * @param array<string, mixed> $options Additional options (size, quality, style)
     */
    public static function paint(
        string $prompt,
        ?string $model = null,
        array $options = [],
    ): Image {
        $model ??= 'gpt-image-1.5';
        $provider = self::detectProvider($model);
        $providerInstance = self::getProvider($provider);

        if (!$providerInstance instanceof ImageGenerationInterface) {
            throw new ConfigurationException("Provider '{$provider}' does not support image generation");
        }

        $options['model'] = $model;
        return $providerInstance->generateImage($prompt, $options);
    }

    /**
     * Generate multiple images from a prompt.
     *
     * @param string $prompt Description of the images to generate
     * @param int $count Number of images to generate
     * @param string|null $model Image model (default: gpt-image-1.5)
     * @param array<string, mixed> $options Additional options
     * @return array<Image>
     */
    public static function paintMany(
        string $prompt,
        int $count = 2,
        ?string $model = null,
        array $options = [],
    ): array {
        $model ??= 'gpt-image-1.5';
        $provider = self::detectProvider($model);
        $providerInstance = self::getProvider($provider);

        if (!$providerInstance instanceof ImageGenerationInterface) {
            throw new ConfigurationException("Provider '{$provider}' does not support image generation");
        }

        $options['model'] = $model;
        return $providerInstance->generateImages($prompt, $count, $options);
    }

    /**
     * Register a custom provider.
     *
     * @param class-string<ProviderInterface> $providerClass
     */
    public static function registerProvider(string $slug, string $providerClass): void
    {
        self::$providers[$slug] = $providerClass;
    }

    /**
     * Register a model to provider mapping.
     */
    public static function registerModel(string $model, string $provider): void
    {
        self::$modelProviders[$model] = $provider;
    }

    /**
     * Get a provider instance.
     */
    public static function getProvider(string $slug): ProviderInterface
    {
        $providerClass = self::$providers[$slug] ?? null;

        if ($providerClass === null) {
            throw ConfigurationException::invalidProvider($slug);
        }

        return new $providerClass();
    }

    /**
     * Detect provider from model name.
     */
    private static function detectProvider(string $model): string
    {
        // Check exact match first
        if (isset(self::$modelProviders[$model])) {
            return self::$modelProviders[$model];
        }

        // Check prefix patterns for OpenAI
        if (str_starts_with($model, 'gpt-') || preg_match('/^o[1-4]/', $model)) {
            return 'openai';
        }

        if (str_starts_with($model, 'text-embedding-') || str_starts_with($model, 'dall-e') || str_starts_with($model, 'gpt-image')) {
            return 'openai';
        }

        if (str_starts_with($model, 'claude-')) {
            return 'anthropic';
        }

        if (str_starts_with($model, 'gemini-')) {
            return 'gemini';
        }

        // Ollama local models
        if (preg_match('/^(llama|codellama|mistral|mixtral|phi|gemma|qwen|deepseek-coder|vicuna|orca|neural-chat)/', $model)) {
            return 'ollama';
        }

        // Models with : are typically Ollama (e.g., llama3.2:1b, mistral:7b)
        if (str_contains($model, ':')) {
            return 'ollama';
        }

        // Default to configured provider or OpenAI
        return Configuration::getInstance()->getDefaultProvider() ?? 'openai';
    }

    /**
     * List available providers.
     *
     * @return array<string>
     */
    public static function listProviders(): array
    {
        return array_keys(self::$providers);
    }

    /**
     * Reset for testing.
     */
    public static function reset(): void
    {
        Configuration::reset();
    }
}
