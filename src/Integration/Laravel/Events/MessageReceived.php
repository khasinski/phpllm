<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PHPLLM\Core\Message;

/**
 * Fired when a response is received from the AI provider.
 */
class MessageReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Message $response,
        public readonly string $model,
        public readonly ?object $conversation = null,
    ) {
    }
}
