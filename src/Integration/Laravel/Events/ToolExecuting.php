<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PHPLLM\Core\ToolCall;

/**
 * Fired before a tool is executed.
 */
class ToolExecuting
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly string $model,
    ) {
    }
}
