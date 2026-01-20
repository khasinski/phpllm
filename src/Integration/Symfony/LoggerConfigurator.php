<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Symfony;

use PHPLLM\Core\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Configures PHPLLM logging with Symfony's logger service.
 *
 * This event subscriber sets up the PHPLLM Logger on the first request,
 * ensuring the logger is available for all API calls.
 */
final class LoggerConfigurator implements EventSubscriberInterface
{
    private bool $configured = false;

    public function __construct(
        private readonly ?LoggerInterface $logger,
        private readonly bool $enabled,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255], // High priority, run early
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($this->configured) {
            return;
        }

        if ($this->enabled && $this->logger !== null) {
            Logger::setLogger($this->logger);
        }

        $this->configured = true;
    }
}
