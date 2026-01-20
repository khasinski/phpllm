<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Represents a message in a conversation.
 */
final class Message
{
    /**
     * @param array<ToolCall> $toolCalls
     */
    public function __construct(
        public readonly Role $role,
        public readonly Content $content,
        public readonly ?string $model = null,
        public readonly array $toolCalls = [],
        public readonly ?string $toolCallId = null,
        public readonly ?Tokens $tokens = null,
        public readonly ?string $stopReason = null,
        public readonly ?string $thinking = null,
    ) {
    }

    /**
     * Create a system message.
     */
    public static function system(string $content): self
    {
        return new self(
            role: Role::System,
            content: Content::text($content),
        );
    }

    /**
     * Create a user message.
     *
     * @param string|array<string>|Attachment|array<Attachment>|null $with
     */
    public static function user(string $text, string|array|Attachment|null $with = null): self
    {
        $content = $with !== null
            ? Content::with($text, $with)
            : Content::text($text);

        return new self(
            role: Role::User,
            content: $content,
        );
    }

    /**
     * Create an assistant message.
     *
     * @param array<ToolCall> $toolCalls
     */
    public static function assistant(
        string $text,
        array $toolCalls = [],
        ?Tokens $tokens = null,
        ?string $model = null,
        ?string $stopReason = null,
        ?string $thinking = null,
    ): self {
        return new self(
            role: Role::Assistant,
            content: Content::text($text),
            model: $model,
            toolCalls: $toolCalls,
            tokens: $tokens,
            stopReason: $stopReason,
            thinking: $thinking,
        );
    }

    /**
     * Create a tool result message.
     *
     * @param mixed $result
     */
    public static function toolResult(string $toolCallId, mixed $result): self
    {
        $content = is_string($result) ? $result : json_encode($result);

        return new self(
            role: Role::Tool,
            content: Content::text($content),
            toolCallId: $toolCallId,
        );
    }

    /**
     * Get the text content of the message.
     */
    public function getText(): string
    {
        return $this->content->toString();
    }

    /**
     * Check if the message has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Check if the message is from a tool.
     */
    public function isToolResult(): bool
    {
        return $this->role === Role::Tool;
    }

    /**
     * Check if the message has thinking content (extended thinking).
     */
    public function hasThinking(): bool
    {
        return $this->thinking !== null;
    }

    /**
     * Convert to array representation (for API requests).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role->value,
            'content' => $this->content->toString(),
        ];

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        if ($this->hasToolCalls()) {
            $data['tool_calls'] = array_map(fn(ToolCall $tc) => $tc->toArray(), $this->toolCalls);
        }

        return $data;
    }
}
