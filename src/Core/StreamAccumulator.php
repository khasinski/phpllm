<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use PHPLLM\Exceptions\StreamException;

/**
 * Accumulates streaming chunks into a complete message.
 *
 * Includes memory safety limits to prevent runaway memory usage.
 */
final class StreamAccumulator
{
    // Default limits (can be overridden via constructor)
    private const DEFAULT_MAX_CONTENT_SIZE = 10 * 1024 * 1024; // 10 MB
    private const DEFAULT_MAX_TOOL_CALLS = 100;
    private const DEFAULT_MAX_ARGUMENTS_SIZE = 1024 * 1024; // 1 MB per tool

    private string $content = '';
    private string $thinking = '';

    /** @var array<string, array{id: string, name: string, arguments: string}> */
    private array $toolCalls = [];

    private ?string $stopReason = null;
    private ?Tokens $tokens = null;

    private int $maxContentSize;
    private int $maxToolCalls;
    private int $maxArgumentsSize;

    public function __construct(
        ?int $maxContentSize = null,
        ?int $maxToolCalls = null,
        ?int $maxArgumentsSize = null,
    ) {
        $this->maxContentSize = $maxContentSize ?? self::DEFAULT_MAX_CONTENT_SIZE;
        $this->maxToolCalls = $maxToolCalls ?? self::DEFAULT_MAX_TOOL_CALLS;
        $this->maxArgumentsSize = $maxArgumentsSize ?? self::DEFAULT_MAX_ARGUMENTS_SIZE;
    }

    /**
     * Add a chunk to the accumulator.
     *
     * @throws StreamException If content exceeds size limits
     */
    public function add(Chunk $chunk): void
    {
        if ($chunk->content !== null) {
            $newSize = strlen($this->content) + strlen($chunk->content);
            if ($newSize > $this->maxContentSize) {
                Logger::error('Stream content exceeded maximum size', [
                    'current_size' => strlen($this->content),
                    'chunk_size' => strlen($chunk->content),
                    'max_size' => $this->maxContentSize,
                ]);
                throw new StreamException(
                    "Stream content exceeded maximum size of {$this->maxContentSize} bytes",
                );
            }
            $this->content .= $chunk->content;
        }

        if ($chunk->thinking !== null) {
            $newSize = strlen($this->thinking) + strlen($chunk->thinking);
            if ($newSize > $this->maxContentSize) {
                Logger::error('Stream thinking content exceeded maximum size', [
                    'current_size' => strlen($this->thinking),
                    'max_size' => $this->maxContentSize,
                ]);
                throw new StreamException(
                    "Stream thinking content exceeded maximum size of {$this->maxContentSize} bytes",
                );
            }
            $this->thinking .= $chunk->thinking;
        }

        foreach ($chunk->toolCalls as $toolCall) {
            $id = $toolCall->id;
            if (!isset($this->toolCalls[$id])) {
                if (count($this->toolCalls) >= $this->maxToolCalls) {
                    Logger::error('Too many tool calls in stream', [
                        'count' => count($this->toolCalls),
                        'max' => $this->maxToolCalls,
                    ]);
                    throw new StreamException(
                        "Stream exceeded maximum of {$this->maxToolCalls} tool calls",
                    );
                }
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

                $newSize = strlen($this->toolCalls[$id]['arguments']) + strlen($args);
                if ($newSize > $this->maxArgumentsSize) {
                    Logger::error('Tool arguments exceeded maximum size', [
                        'tool_id' => $id,
                        'current_size' => strlen($this->toolCalls[$id]['arguments']),
                        'max_size' => $this->maxArgumentsSize,
                    ]);
                    throw new StreamException(
                        "Tool arguments exceeded maximum size of {$this->maxArgumentsSize} bytes",
                    );
                }
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
