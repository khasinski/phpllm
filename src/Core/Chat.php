<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use PHPLLM\Contracts\ProviderInterface;
use PHPLLM\Contracts\ToolInterface;
use PHPLLM\Exceptions\ToolExecutionException;

/**
 * Main chat interface for interacting with LLMs.
 *
 * Provides a fluent API for building and executing conversations.
 *
 * Example:
 * ```php
 * $chat = new Chat($provider, 'gpt-4');
 * $response = $chat
 *     ->withInstructions('You are a helpful assistant')
 *     ->withTemperature(0.7)
 *     ->ask('What is PHP?');
 * ```
 */
final class Chat
{
    /** @var array<Message> */
    private array $messages = [];

    /** @var array<ToolInterface> */
    private array $tools = [];

    private ?string $instructions = null;
    private ?float $temperature = null;
    private ?int $maxTokens = null;

    /** @var array<string, string> */
    private array $customHeaders = [];

    /** @var callable|null */
    private mixed $onNewMessage = null;

    /** @var callable|null */
    private mixed $onToolCall = null;

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly string $model,
    ) {
    }

    /**
     * Set system instructions for the conversation.
     */
    public function withInstructions(string $instructions): self
    {
        $this->instructions = $instructions;
        return $this;
    }

    /**
     * Add a tool that the LLM can call.
     *
     * @param ToolInterface|class-string<ToolInterface> $tool
     */
    public function withTool(ToolInterface|string $tool): self
    {
        if (is_string($tool)) {
            $tool = new $tool();
        }

        $this->tools[$tool->getName()] = $tool;
        return $this;
    }

    /**
     * Add multiple tools.
     *
     * @param array<ToolInterface|class-string<ToolInterface>> $tools
     */
    public function withTools(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->withTool($tool);
        }
        return $this;
    }

    /**
     * Set the temperature for responses.
     *
     * @param float $temperature Value between 0.0 and 2.0
     *
     * @throws \InvalidArgumentException If temperature is out of range
     */
    public function withTemperature(float $temperature): self
    {
        if ($temperature < 0.0 || $temperature > 2.0) {
            throw new \InvalidArgumentException('Temperature must be between 0.0 and 2.0');
        }
        $this->temperature = $temperature;
        return $this;
    }

    /**
     * Set the maximum tokens for responses.
     *
     * @param int $maxTokens Positive integer
     *
     * @throws \InvalidArgumentException If maxTokens is not positive
     */
    public function withMaxTokens(int $maxTokens): self
    {
        if ($maxTokens < 1) {
            throw new \InvalidArgumentException('Max tokens must be at least 1');
        }
        $this->maxTokens = $maxTokens;
        return $this;
    }

    /**
     * Add custom headers to API requests.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->customHeaders = array_merge($this->customHeaders, $headers);
        return $this;
    }

    /**
     * Set callback for new messages.
     *
     * @param callable(Message): void $callback
     */
    public function onNewMessage(callable $callback): self
    {
        $this->onNewMessage = $callback;
        return $this;
    }

    /**
     * Set callback for tool calls.
     *
     * @param callable(ToolCall, mixed): void $callback
     */
    public function onToolCall(callable $callback): self
    {
        $this->onToolCall = $callback;
        return $this;
    }

    /**
     * Send a message and get a response.
     *
     * Use named arguments for explicit content types:
     * - $image: Image file path, URL, or Attachment
     * - $pdf: PDF file path or Attachment
     * - $audio: Audio file path or Attachment
     * - $file: Any file (auto-detected type)
     *
     * Example:
     * ```php
     * $chat->ask('What is in this image?', image: '/path/to/photo.jpg');
     * $chat->ask('Summarize this', pdf: '/path/to/doc.pdf');
     * ```
     *
     * @param string|array<string>|Attachment|array<Attachment>|null $image Image attachment(s)
     * @param string|array<string>|Attachment|array<Attachment>|null $pdf PDF attachment(s)
     * @param string|array<string>|Attachment|array<Attachment>|null $audio Audio attachment(s)
     * @param string|array<string>|Attachment|array<Attachment>|null $file Any file attachment(s)
     * @param callable(Chunk): void|null $stream Streaming callback
     */
    public function ask(
        string $message,
        string|array|Attachment|null $image = null,
        string|array|Attachment|null $pdf = null,
        string|array|Attachment|null $audio = null,
        string|array|Attachment|null $file = null,
        ?callable $stream = null,
    ): Message {
        // Collect all attachments
        $attachments = $this->collectAttachments($image, $pdf, $audio, $file);

        // Add user message
        $userMessage = $attachments !== null
            ? Message::user($message, $attachments)
            : Message::user($message);
        $this->addMessage($userMessage);

        // Complete and handle tool calls
        return $this->completeWithToolCalls($stream);
    }

    /**
     * Collect attachments from named parameters.
     *
     * @return array<Attachment>|null
     */
    private function collectAttachments(
        string|array|Attachment|null $image,
        string|array|Attachment|null $pdf,
        string|array|Attachment|null $audio,
        string|array|Attachment|null $file,
    ): ?array {
        $attachments = [];

        foreach ([$image, $pdf, $audio, $file] as $input) {
            if ($input === null) {
                continue;
            }

            if ($input instanceof Attachment) {
                $attachments[] = $input;
            } elseif (is_string($input)) {
                $attachments[] = $this->createAttachment($input);
            } elseif (is_array($input)) {
                foreach ($input as $item) {
                    $attachments[] = $item instanceof Attachment
                        ? $item
                        : $this->createAttachment($item);
                }
            }
        }

        return empty($attachments) ? null : $attachments;
    }

    /**
     * Create an attachment from a string (path or URL).
     */
    private function createAttachment(string $value): Attachment
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return Attachment::fromUrl($value);
        }

        return Attachment::fromPath($value);
    }

    /**
     * Add a message to the conversation.
     */
    public function addMessage(Message $message): self
    {
        $this->messages[] = $message;

        if ($this->onNewMessage !== null) {
            ($this->onNewMessage)($message);
        }

        return $this;
    }

    /**
     * Get all messages in the conversation.
     *
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the last message in the conversation.
     */
    public function getLastMessage(): ?Message
    {
        return $this->messages[count($this->messages) - 1] ?? null;
    }

    /**
     * Clear the conversation history.
     */
    public function clear(): self
    {
        $this->messages = [];
        return $this;
    }

    /**
     * Complete the conversation, handling tool calls automatically.
     */
    private function completeWithToolCalls(?callable $stream = null): Message
    {
        $maxIterations = 10; // Prevent infinite loops
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $iterations++;

            $response = $stream !== null
                ? $this->streamCompletion($stream)
                : $this->complete();

            $this->addMessage($response);

            // If no tool calls, we're done
            if (!$response->hasToolCalls()) {
                return $response;
            }

            // Execute tool calls
            foreach ($response->toolCalls as $toolCall) {
                $result = $this->executeTool($toolCall);

                // Add tool result to conversation
                $toolMessage = Message::toolResult($toolCall->id, $result);
                $this->addMessage($toolMessage);
            }
        }

        // Return last response if max iterations reached
        return $this->getLastMessage() ?? throw new \RuntimeException('No response received');
    }

    /**
     * Execute a single completion request.
     */
    private function complete(): Message
    {
        $messages = $this->buildMessages();
        $options = $this->buildOptions();

        return $this->provider->complete($messages, $options);
    }

    /**
     * Execute a streaming completion request.
     *
     * @param callable(Chunk): void $callback
     */
    private function streamCompletion(callable $callback): Message
    {
        $messages = $this->buildMessages();
        $options = $this->buildOptions();

        $accumulator = new StreamAccumulator();

        foreach ($this->provider->stream($messages, $options) as $chunk) {
            $callback($chunk);
            $accumulator->add($chunk);
        }

        return $accumulator->toMessage($this->model);
    }

    /**
     * Execute a tool and return the result.
     *
     * @throws ToolExecutionException If tool is unknown or execution fails
     */
    private function executeTool(ToolCall $toolCall): mixed
    {
        $tool = $this->tools[$toolCall->name] ?? null;

        if ($tool === null) {
            throw ToolExecutionException::unknownTool($toolCall, array_keys($this->tools));
        }

        try {
            $result = $tool->execute($toolCall->arguments);

            if ($this->onToolCall !== null) {
                ($this->onToolCall)($toolCall, $result);
            }

            return $result;
        } catch (ToolExecutionException $e) {
            // Re-throw if already a ToolExecutionException
            throw $e;
        } catch (\Exception $e) {
            throw ToolExecutionException::fromException($e, $toolCall);
        }
    }

    /**
     * Build the messages array including system instructions.
     *
     * @return array<Message>
     */
    private function buildMessages(): array
    {
        $messages = [];

        if ($this->instructions !== null) {
            $messages[] = Message::system($this->instructions);
        }

        return array_merge($messages, $this->messages);
    }

    /**
     * Build options for the API request.
     *
     * @return array<string, mixed>
     */
    private function buildOptions(): array
    {
        $options = [
            'model' => $this->model,
        ];

        if ($this->temperature !== null) {
            $options['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $options['max_tokens'] = $this->maxTokens;
        }

        if (!empty($this->tools)) {
            $options['tools'] = array_map(
                fn (ToolInterface $tool) => $tool->toSchema(),
                array_values($this->tools),
            );
        }

        if (!empty($this->customHeaders)) {
            $options['headers'] = $this->customHeaders;
        }

        return $options;
    }
}
