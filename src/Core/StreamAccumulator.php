<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Accumulates streaming chunks into a complete message.
 */
final class StreamAccumulator
{
    private string $content = '';
    private string $thinking = '';

    /** @var array<string, array{id: string, name: string, arguments: string}> */
    private array $toolCalls = [];

    private ?string $stopReason = null;
    private ?Tokens $tokens = null;

    /**
     * Add a chunk to the accumulator.
     */
    public function add(Chunk $chunk): void
    {
        if ($chunk->content !== null) {
            $this->content .= $chunk->content;
        }

        if ($chunk->thinking !== null) {
            $this->thinking .= $chunk->thinking;
        }

        foreach ($chunk->toolCalls as $toolCall) {
            $id = $toolCall->id;
            if (!isset($this->toolCalls[$id])) {
                $this->toolCalls[$id] = [
                    'id' => $id,
                    'name' => $toolCall->name,
                    'arguments' => '',
                ];
            }

            // Accumulate arguments (they come in chunks as JSON string)
            if (!empty($toolCall->arguments)) {
                $args = is_array($toolCall->arguments)
                    ? json_encode($toolCall->arguments)
                    : $toolCall->arguments;
                $this->toolCalls[$id]['arguments'] .= $args;
            }
        }

        if ($chunk->stopReason !== null) {
            $this->stopReason = $chunk->stopReason;
        }

        if ($chunk->tokens !== null) {
            $this->tokens = $chunk->tokens;
        }
    }

    /**
     * Convert accumulated data to a Message.
     */
    public function toMessage(?string $model = null): Message
    {
        $toolCalls = [];
        foreach ($this->toolCalls as $tc) {
            $arguments = json_decode($tc['arguments'], true) ?? [];
            $toolCalls[] = new ToolCall($tc['id'], $tc['name'], $arguments);
        }

        return Message::assistant(
            text: $this->content,
            toolCalls: $toolCalls,
            tokens: $this->tokens,
            model: $model,
            stopReason: $this->stopReason,
            thinking: $this->thinking !== '' ? $this->thinking : null,
        );
    }

    /**
     * Get the accumulated content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the accumulated thinking content.
     */
    public function getThinking(): string
    {
        return $this->thinking;
    }
}
