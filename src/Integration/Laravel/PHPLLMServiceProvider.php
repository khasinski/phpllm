<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel;

use Illuminate\Support\ServiceProvider;
use PHPLLM\Core\Configuration;
use PHPLLM\Integration\Laravel\Console\MakeToolCommand;
use PHPLLM\Integration\Laravel\Console\VerifyCommand;
use PHPLLM\PHPLLM;

/**
 * Laravel service provider for PHPLLM.
 *
 * Provides automatic configuration from Laravel's config system.
 */
class PHPLLMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/phpllm.php', 'phpllm');

        $this->app->singleton(Configuration::class, function () {
            return Configuration::getInstance();
        });

        $this->app->singleton('phpllm', function () {
            return new class () {
                public function chat(?string $model = null, ?string $provider = null)
                {
                    return PHPLLM::chat($model, $provider);
                }

                public function embed(string|array $input, ?string $model = null, array $options = [])
                {
                    return PHPLLM::embed($input, $model, $options);
                }

                public function paint(string $prompt, ?string $model = null, array $options = [])
                {
                    return PHPLLM::paint($prompt, $model, $options);
                }
            };
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/config/phpllm.php' => config_path('phpllm.php'),
        ], 'phpllm-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/database/migrations' => database_path('migrations'),
        ], 'phpllm-migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyCommand::class,
                MakeToolCommand::class,
            ]);
        }

        // Configure PHPLLM from Laravel config
        $config = config('phpllm', []);

        PHPLLM::configure([
            'openai_api_key' => $config['openai_api_key'] ?? env('OPENAI_API_KEY'),
            'anthropic_api_key' => $config['anthropic_api_key'] ?? env('ANTHROPIC_API_KEY'),
            'gemini_api_key' => $config['gemini_api_key'] ?? env('GEMINI_API_KEY'),
            'default_model' => $config['default_model'] ?? 'gpt-4o-mini',
            'request_timeout' => $config['request_timeout'] ?? 120,
            'max_retries' => $config['max_retries'] ?? 3,
        ]);
    }
}
