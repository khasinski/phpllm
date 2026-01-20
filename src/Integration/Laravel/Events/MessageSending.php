<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PHPLLM\Core\Message;

/**
 * Fired before a message is sent to the AI provider.
 */
class MessageSending
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly string $model,
        public readonly ?object $conversation = null,
    ) {
    }
}
