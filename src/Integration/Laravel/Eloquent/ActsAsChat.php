<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPLLM\Core\Chat;
use PHPLLM\Core\Message;
use PHPLLM\PHPLLM;

/**
 * Trait for Eloquent models that represent chat conversations.
 *
 * Usage:
 * ```php
 * class Conversation extends Model
 * {
 *     use ActsAsChat;
 *
 *     protected string $messagesRelation = 'messages';
 *     protected string $modelColumn = 'ai_model';
 * }
 * ```
 */
trait ActsAsChat
{
    protected ?Chat $chatInstance = null;

    /**
     * Boot the trait.
     */
    public static function bootActsAsChat(): void
    {
        // Auto-save messages after asking
    }

    /**
     * Get the messages relationship name.
     */
    protected function getMessagesRelation(): string
    {
        return $this->messagesRelation ?? 'messages';
    }

    /**
     * Get the model column name.
     */
    protected function getModelColumn(): string
    {
        return $this->modelColumn ?? 'model';
    }

    /**
     * Get or create the Chat instance.
     */
    public function chat(): Chat
    {
        if ($this->chatInstance === null) {
            $model = $this->{$this->getModelColumn()} ?? 'gpt-4o-mini';
            $this->chatInstance = PHPLLM::chat($model);

            // Load existing messages
            $this->loadMessagesIntoChat();
        }

        return $this->chatInstance;
    }

    /**
     * Ask a question and persist the conversation.
     *
     * @param string|array|null $with Attachments
     * @param callable|null $stream Streaming callback
     */
    public function ask(string $message, string|array|null $with = null, ?callable $stream = null): Message
    {
        $response = $this->chat()->ask($message, $with, $stream);

        // Persist the user message
        $this->persistMessage(Message::user($message, $with));

        // Persist the assistant response
        $this->persistMessage($response);

        return $response;
    }

    /**
     * Set system instructions.
     */
    public function withInstructions(string $instructions): static
    {
        $this->chat()->withInstructions($instructions);
        return $this;
    }

    /**
     * Add a tool.
     */
    public function withTool($tool): static
    {
        $this->chat()->withTool($tool);
        return $this;
    }

    /**
     * Load persisted messages into the chat.
     */
    protected function loadMessagesIntoChat(): void
    {
        $relation = $this->getMessagesRelation();

        if (!method_exists($this, $relation)) {
            return;
        }

        /** @var HasMany $messages */
        $messages = $this->{$relation}()->orderBy('created_at')->get();

        foreach ($messages as $messageModel) {
            $message = $this->hydrateMessage($messageModel);
            if ($message !== null) {
                $this->chatInstance->addMessage($message);
            }
        }
    }

    /**
     * Persist a message to the database.
     */
    protected function persistMessage(Message $message): void
    {
        $relation = $this->getMessagesRelation();

        if (!method_exists($this, $relation)) {
            return;
        }

        $this->{$relation}()->create([
            'role' => $message->role->value,
            'content' => $message->getText(),
            'model' => $message->model,
            'tokens_input' => $message->tokens?->input,
            'tokens_output' => $message->tokens?->output,
            'tool_calls' => $message->hasToolCalls()
                ? json_encode(array_map(fn($tc) => $tc->toArray(), $message->toolCalls))
                : null,
            'tool_call_id' => $message->toolCallId,
        ]);
    }

    /**
     * Hydrate a Message from an Eloquent model.
     */
    protected function hydrateMessage(Model $model): ?Message
    {
        $role = $model->role ?? null;
        $content = $model->content ?? '';

        if ($role === null) {
            return null;
        }

        return match ($role) {
            'system' => Message::system($content),
            'user' => Message::user($content),
            'assistant' => Message::assistant($content),
            'tool' => Message::toolResult($model->tool_call_id ?? '', $content),
            default => null,
        };
    }

    /**
     * Clear the conversation.
     */
    public function clearConversation(): static
    {
        $relation = $this->getMessagesRelation();

        if (method_exists($this, $relation)) {
            $this->{$relation}()->delete();
        }

        $this->chatInstance = null;

        return $this;
    }
}
