<?php

declare(strict_types=1);

use PHPLLM\Core\Configuration;
use PHPLLM\PHPLLM;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Configuration::class)
        ->factory([Configuration::class, 'getInstance'])
        ->public();

    $services->set('phpllm', PHPLLM::class)
        ->public();

    $services->alias(PHPLLM::class, 'phpllm');
};
