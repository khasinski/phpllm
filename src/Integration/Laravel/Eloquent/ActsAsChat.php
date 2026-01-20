<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use PHPLLM\Core\Chat;
use PHPLLM\Core\Message;
use PHPLLM\Exceptions\ApiException;
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
 *
 *     public function messages(): HasMany
 *     {
 *         return $this->hasMany(Message::class);
 *     }
 * }
 * ```
 *
 * Required migration for messages table:
 * ```php
 * Schema::create('messages', function (Blueprint $table) {
 *     $table->id();
 *     $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
 *     $table->string('role'); // system, user, assistant, tool
 *     $table->longText('content');
 *     $table->string('model')->nullable();
 *     $table->unsignedInteger('tokens_input')->nullable();
 *     $table->unsignedInteger('tokens_output')->nullable();
 *     $table->json('tool_calls')->nullable();
 *     $table->string('tool_call_id')->nullable();
 *     $table->timestamps();
 * });
 * ```
 */
trait ActsAsChat
{
    protected ?Chat $chatInstance = null;

    /**
     * Boot the trait - auto-delete messages when conversation is deleted.
     */
    public static function bootActsAsChat(): void
    {
        static::deleting(function (Model $model): void {
            /** @var Model&ActsAsChat $model */
            $relation = $model->getMessagesRelation();
            if (method_exists($model, $relation)) {
                $model->{$relation}()->delete();
            }
        });
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
     *
     * @throws ApiException
     */
    public function ask(string $message, string|array|null $with = null, ?callable $stream = null): Message
    {
        $userMessage = Message::user($message, $with);

        // Get response from AI
        $response = $this->chat()->ask($message, $with, $stream);

        // Persist both messages in a transaction
        DB::transaction(function () use ($userMessage, $response): void {
            $this->persistMessage($userMessage);
            $this->persistMessage($response);
        });

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
                ? json_encode(array_map(fn ($tc) => $tc->toArray(), $message->toolCalls))
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
