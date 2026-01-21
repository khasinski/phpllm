<?php

declare(strict_types=1);

namespace PHPLLM\Providers\Ollama;

use PHPLLM\Core\Chunk;
use PHPLLM\Core\Content;
use PHPLLM\Core\Message;
use PHPLLM\Core\Role;
use PHPLLM\Core\Tokens;
use PHPLLM\Core\ToolCall;
use PHPLLM\Providers\BaseProvider;

/**
 * Ollama API provider for local models.
 *
 * Supports Llama, Mistral, CodeLlama, and other local models via Ollama.
 * Default endpoint: http://localhost:11434
 */
class OllamaProvider extends BaseProvider
{
    protected array $capabilities = ['chat', 'vision', 'tools', 'streaming'];

    public function getSlug(): string
    {
        return 'ollama';
    }

    public function getApiBase(): string
    {
        return $this->config->getOllamaApiBase();
    }

    protected function getApiKey(): ?string
    {
        // Ollama doesn't require API key for local usage
        return 'ollama-local';
    }

    public function isConfigured(): bool
    {
        // Ollama is considered configured if the base URL is set
        return true;
    }

    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function getCompletionEndpoint(): string
    {
        return '/api/chat';
    }

    protected function renderPayload(array $messages, array $options): array
    {
        $payload = [
            'model' => $options['model'] ?? 'llama3.2',
            'messages' => array_map([$this, 'renderMessage'], $messages),
            'stream' => $options['stream'] ?? false,
            'options' => [],
        ];

        if (isset($options['temperature'])) {
            $payload['options']['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $payload['options']['num_predict'] = $options['max_tokens'];
        }

        // Remove empty options array
        if (empty($payload['options'])) {
            unset($payload['options']);
        }

        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = array_map([$this, 'renderTool'], $options['tools']);
        }

        return $payload;
    }

    /**
     * Render a message for the Ollama API.
     *
     * @return array<string, mixed>
     */
    private function renderMessage(Message $message): array
    {
        $data = [
            'role' => $message->role->value,
            'content' => $message->getText(),
        ];

        // Handle tool results
        if ($message->role === Role::Tool) {
            $data['role'] = 'tool';
            $data['tool_call_id'] = $message->toolCallId;
        }

        // Handle multimodal content (images)
        if ($message->content->hasAttachments()) {
            $images = [];
            foreach ($message->content->attachments as $attachment) {
                if ($attachment->isImage()) {
                    $images[] = $attachment->getBase64();
                }
            }
            if (!empty($images)) {
                $data['images'] = $images;
            }
        }

        // Handle tool calls in assistant messages
        if ($message->hasToolCalls()) {
            $data['tool_calls'] = array_map(function (ToolCall $tc) {
                return [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->name,
                        'arguments' => $tc->arguments,
                    ],
                ];
            }, $message->toolCalls);
        }

        return $data;
    }

    /**
     * Render a tool for Ollama format.
     *
     * @param array<string, mixed> $tool
     * @return array<string, mixed>
     */
    private function renderTool(array $tool): array
    {
        return [
            'type' => 'function',
            'function' => $tool['function'] ?? $tool,
        ];
    }

    protected function parseCompletion(array $response): Message
    {
        $messageData = $response['message'] ?? [];

        $toolCalls = [];
        if (isset($messageData['tool_calls'])) {
            foreach ($messageData['tool_calls'] as $tc) {
                $toolCalls[] = new ToolCall(
                    id: $tc['id'] ?? uniqid('tool_'),
                    name: $tc['function']['name'],
                    arguments: $tc['function']['arguments'] ?? [],
                );
            }
        }

        $tokens = null;
        if (isset($response['prompt_eval_count']) || isset($response['eval_count'])) {
            $tokens = Tokens::fromArray([
                'input' => $response['prompt_eval_count'] ?? 0,
                'output' => $response['eval_count'] ?? 0,
            ]);
        }

        return Message::assistant(
            text: $messageData['content'] ?? '',
            toolCalls: $toolCalls,
            tokens: $tokens,
            model: $response['model'] ?? null,
            stopReason: $response['done'] ? 'stop' : null,
        );
    }

    protected function parseStreamChunk(array $data): Chunk
    {
        $messageData = $data['message'] ?? [];

        $toolCalls = [];
        if (isset($messageData['tool_calls'])) {
            foreach ($messageData['tool_calls'] as $tc) {
                $toolCalls[] = new ToolCall(
                    id: $tc['id'] ?? uniqid('tool_'),
                    name: $tc['function']['name'],
                    arguments: $tc['function']['arguments'] ?? [],
                );
            }
        }

        $tokens = null;
        if (isset($data['prompt_eval_count']) || isset($data['eval_count'])) {
            $tokens = Tokens::fromArray([
                'input' => $data['prompt_eval_count'] ?? 0,
                'output' => $data['eval_count'] ?? 0,
            ]);
        }

        return new Chunk(
            content: $messageData['content'] ?? null,
            toolCalls: $toolCalls,
            stopReason: ($data['done'] ?? false) ? 'stop' : null,
            tokens: $tokens,
            isLast: $data['done'] ?? false,
        );
    }

    public function listModels(): array
    {
        // These are common models, actual availability depends on what's pulled
        return [
            // Llama models
            'llama3.2' => ['context' => 128000, 'vision' => true, 'tools' => true],
            'llama3.2:1b' => ['context' => 128000, 'vision' => false, 'tools' => true],
            'llama3.1' => ['context' => 128000, 'vision' => false, 'tools' => true],
            'llama3.1:70b' => ['context' => 128000, 'vision' => false, 'tools' => true],

            // Code models
            'codellama' => ['context' => 16000, 'vision' => false, 'tools' => false],
            'deepseek-coder-v2' => ['context' => 128000, 'vision' => false, 'tools' => true],

            // Mistral models
            'mistral' => ['context' => 32000, 'vision' => false, 'tools' => true],
            'mixtral' => ['context' => 32000, 'vision' => false, 'tools' => true],

            // Other popular models
            'phi3' => ['context' => 128000, 'vision' => false, 'tools' => false],
            'gemma2' => ['context' => 8000, 'vision' => false, 'tools' => false],
            'qwen2.5' => ['context' => 128000, 'vision' => false, 'tools' => true],
        ];
    }

    /**
     * List models actually available on the Ollama server.
     *
     * @return array<string, array<string, mixed>>
     */
    public function listAvailableModels(): array
    {
        try {
            $url = $this->getApiBase() . '/api/tags';
            $response = $this->connection->get($url, $this->getHeaders());

            $models = [];
            foreach ($response['models'] ?? [] as $model) {
                $models[$model['name']] = [
                    'size' => $model['size'] ?? null,
                    'modified' => $model['modified_at'] ?? null,
                ];
            }

            return $models;
        } catch (\Exception $e) {
            return [];
        }
    }
}
