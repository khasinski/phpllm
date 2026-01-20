<?php

declare(strict_types=1);

use PHPLLM\Core\Configuration;
use PHPLLM\Core\Logger;
use PHPLLM\Integration\Symfony\LoggerConfigurator;
use PHPLLM\PHPLLM;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Configuration::class)
        ->factory([Configuration::class, 'getInstance'])
        ->public();

    $services->set('phpllm', PHPLLM::class)
        ->public();

    $services->alias(PHPLLM::class, 'phpllm');

    // Logger configurator - sets up PHPLLM logging with Symfony's logger
    $services->set(LoggerConfigurator::class)
        ->args([
            service(LoggerInterface::class)->nullOnInvalid(),
            param('phpllm.logging_enabled'),
        ])
        ->tag('kernel.event_subscriber');
};
