<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Symfony bundle for PHPLLM.
 */
class PHPLLMBundle extends AbstractBundle
{
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->import(__DIR__ . '/Resources/config/services.php');
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('openai_api_key')->defaultNull()->end()
                ->scalarNode('anthropic_api_key')->defaultNull()->end()
                ->scalarNode('gemini_api_key')->defaultNull()->end()
                ->scalarNode('default_model')->defaultValue('gpt-4o-mini')->end()
                ->integerNode('request_timeout')->defaultValue(120)->end()
                ->integerNode('max_retries')->defaultValue(3)->end()
            ->end();
    }
}
