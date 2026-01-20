<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    */
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_api_base' => env('OPENAI_API_BASE', 'https://api.openai.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Anthropic Configuration
    |--------------------------------------------------------------------------
    */
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Google Gemini Configuration
    |--------------------------------------------------------------------------
    */
    'gemini_api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'default_model' => env('PHPLLM_DEFAULT_MODEL', 'gpt-4o-mini'),
    'default_provider' => env('PHPLLM_DEFAULT_PROVIDER'),

    /*
    |--------------------------------------------------------------------------
    | Request Settings
    |--------------------------------------------------------------------------
    */
    'request_timeout' => env('PHPLLM_TIMEOUT', 120),
    'max_retries' => env('PHPLLM_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable PHPLLM debug logging. When enabled, API requests and responses
    | will be logged to the specified channel. Useful for debugging.
    |
    */
    'logging_enabled' => env('PHPLLM_LOGGING', false),
    'logging_channel' => env('PHPLLM_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Model Aliases
    |--------------------------------------------------------------------------
    |
    | Define convenient aliases for model names. Use these in your code
    | instead of full model identifiers for easier switching.
    |
    */
    'model_aliases' => [
        'fast' => 'gpt-4o-mini',
        'smart' => 'gpt-5.2',
        'claude' => 'claude-sonnet-4-5-20250929',
        'local' => 'llama3.2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Ollama for local model support.
    |
    */
    'ollama_api_base' => env('OLLAMA_API_BASE', 'http://localhost:11434'),
];
