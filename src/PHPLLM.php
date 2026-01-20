<?php

declare(strict_types=1);

namespace PHPLLM;

use PHPLLM\Contracts\ProviderInterface;
use PHPLLM\Core\Chat;
use PHPLLM\Core\Configuration;
use PHPLLM\Exceptions\ConfigurationException;
use PHPLLM\Providers\Anthropic\AnthropicProvider;
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
 * // With specific model
 * $chat = PHPLLM::chat('claude-sonnet-4-20250514');
 *
 * // With attachments
 * $chat->ask('What is in this image?', with: 'photo.jpg');
 * ```
 */
final class PHPLLM
{
    /** @var array<string, class-string<ProviderInterface>> */
    private static array $providers = [
        'openai' => OpenAIProvider::class,
        'anthropic' => AnthropicProvider::class,
        // 'gemini' => GeminiProvider::class,
    ];

    /** @var array<string, string> Model to provider mapping */
    private static array $modelProviders = [
        // OpenAI
        'gpt-4o' => 'openai',
        'gpt-4o-mini' => 'openai',
        'gpt-4-turbo' => 'openai',
        'gpt-4' => 'openai',
        'gpt-3.5-turbo' => 'openai',
        'o1' => 'openai',
        'o1-mini' => 'openai',
        'o1-preview' => 'openai',
        'o3-mini' => 'openai',

        // Anthropic
        'claude-sonnet-4-20250514' => 'anthropic',
        'claude-opus-4-20250514' => 'anthropic',
        'claude-3-5-sonnet-20241022' => 'anthropic',
        'claude-3-5-haiku-20241022' => 'anthropic',
        'claude-3-opus-20240229' => 'anthropic',

        // Gemini
        'gemini-2.0-flash' => 'gemini',
        'gemini-1.5-pro' => 'gemini',
        'gemini-1.5-flash' => 'gemini',
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
     * @param string|null $model Model ID or null for default
     * @param string|null $provider Provider slug or null to auto-detect
     */
    public static function chat(?string $model = null, ?string $provider = null): Chat
    {
        $config = Configuration::getInstance();

        $model = $model ?? $config->getDefaultModel();
        $provider = $provider ?? self::detectProvider($model);

        $providerInstance = self::getProvider($provider);

        return new Chat($providerInstance, $model);
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

        // Check prefix patterns
        if (str_starts_with($model, 'gpt-') || str_starts_with($model, 'o1') || str_starts_with($model, 'o3')) {
            return 'openai';
        }

        if (str_starts_with($model, 'claude-')) {
            return 'anthropic';
        }

        if (str_starts_with($model, 'gemini-')) {
            return 'gemini';
        }

        if (str_starts_with($model, 'mistral-') || str_starts_with($model, 'mixtral-')) {
            return 'mistral';
        }

        if (str_starts_with($model, 'deepseek-')) {
            return 'deepseek';
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
