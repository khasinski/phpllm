<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Symfony;

use PHPLLM\PHPLLM;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Symfony bundle for PHPLLM.
 *
 * Configuration example (config/packages/phpllm.yaml):
 *
 *     phpllm:
 *         openai_api_key: '%env(OPENAI_API_KEY)%'
 *         anthropic_api_key: '%env(ANTHROPIC_API_KEY)%'
 *         default_model: gpt-4o-mini
 *         request_timeout: 120
 *         max_retries: 3
 */
class PHPLLMBundle extends AbstractBundle
{
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // Apply configuration to PHPLLM
        PHPLLM::configure([
            'openai_api_key' => $config['openai_api_key'],
            'anthropic_api_key' => $config['anthropic_api_key'],
            'gemini_api_key' => $config['gemini_api_key'],
            'default_model' => $config['default_model'],
            'request_timeout' => $config['request_timeout'],
            'max_retries' => $config['max_retries'],
            'ollama_api_base' => $config['ollama_api_base'],
            'model_aliases' => $config['model_aliases'],
        ]);

        // Store logging config for runtime setup
        $builder->setParameter('phpllm.logging_enabled', $config['logging_enabled']);

        $container->import(__DIR__ . '/Resources/config/services.php');
    }

    public function boot(): void
    {
        // Note: Logging is configured at runtime via the LoggerConfigurator
        // which is set up in services.php
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('openai_api_key')
                    ->defaultNull()
                    ->info('OpenAI API key')
                ->end()
                ->scalarNode('anthropic_api_key')
                    ->defaultNull()
                    ->info('Anthropic API key')
                ->end()
                ->scalarNode('gemini_api_key')
                    ->defaultNull()
                    ->info('Google Gemini API key')
                ->end()
                ->scalarNode('default_model')
                    ->defaultValue('gpt-4o-mini')
                    ->info('Default model to use for chat')
                ->end()
                ->integerNode('request_timeout')
                    ->defaultValue(120)
                    ->min(1)
                    ->max(600)
                    ->info('Request timeout in seconds')
                ->end()
                ->integerNode('max_retries')
                    ->defaultValue(3)
                    ->min(0)
                    ->max(10)
                    ->info('Maximum number of retries on failure')
                ->end()
                ->scalarNode('ollama_api_base')
                    ->defaultValue('http://localhost:11434')
                    ->info('Ollama API base URL for local models')
                ->end()
                ->booleanNode('logging_enabled')
                    ->defaultFalse()
                    ->info('Enable debug logging for API requests/responses')
                ->end()
                ->arrayNode('model_aliases')
                    ->useAttributeAsKey('alias')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        'fast' => 'gpt-4o-mini',
                        'smart' => 'gpt-5.2',
                        'claude' => 'claude-sonnet-4-5-20250929',
                        'local' => 'llama3.2',
                    ])
                    ->info('Model aliases for convenience')
                ->end()
            ->end();
    }
}
