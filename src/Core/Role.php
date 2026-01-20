<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Message roles in a conversation.
 */
enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
