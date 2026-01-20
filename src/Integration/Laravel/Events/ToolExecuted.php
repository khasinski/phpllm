<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PHPLLM\Core\ToolCall;

/**
 * Fired after a tool has been executed.
 */
class ToolExecuted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly mixed $result,
        public readonly string $model,
    ) {
    }
}
