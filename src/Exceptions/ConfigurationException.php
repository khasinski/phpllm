<?php

declare(strict_types=1);

namespace PHPLLM\Exceptions;

/**
 * Thrown when configuration is invalid or missing.
 */
class ConfigurationException extends PHPLLMException
{
    public static function missingApiKey(string $provider): self
    {
        return new self("API key for {$provider} is not configured. Set it via PHPLLM::configure().");
    }

    public static function invalidProvider(string $provider): self
    {
        return new self("Unknown provider: {$provider}");
    }

    public static function unknownModel(string $model): self
    {
        return new self(
            "Cannot detect provider for model '{$model}'. " .
            "Either use a known model, register the model with PHPLLM::registerModel(), " .
            "or set a default_provider in configuration."
        );
    }
}
