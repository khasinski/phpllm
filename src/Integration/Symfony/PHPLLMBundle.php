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
        ]);

        $container->import(__DIR__ . '/Resources/config/services.php');
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
            ->end();
    }
}
