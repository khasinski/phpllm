<?php

declare(strict_types=1);

namespace PHPLLM\Providers\Anthropic;

use PHPLLM\Core\Chunk;
use PHPLLM\Core\Content;
use PHPLLM\Core\Message;
use PHPLLM\Core\Role;
use PHPLLM\Core\Tokens;
use PHPLLM\Core\ToolCall;
use PHPLLM\Providers\BaseProvider;

/**
 * Anthropic Claude API provider.
 *
 * Supports Claude 3/3.5/4 models with chat, vision, tools, thinking, and streaming.
 */
class AnthropicProvider extends BaseProvider
{
    private const API_VERSION = '2023-06-01';

    protected array $capabilities = ['chat', 'vision', 'tools', 'streaming', 'thinking', 'pdf'];

    public function getSlug(): string
    {
        return 'anthropic';
    }

    public function getApiBase(): string
    {
        return 'https://api.anthropic.com/v1';
    }

    protected function getApiKey(): ?string
    {
        return $this->config->getAnthropicApiKey();
    }

    public function getHeaders(): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === null) {
            throw new \PHPLLM\Exceptions\ConfigurationException('Anthropic API key not configured');
        }

        return [
            'x-api-key' => $apiKey,
            'anthropic-version' => self::API_VERSION,
        ];
    }

    protected function getCompletionEndpoint(): string
    {
        return '/messages';
    }

    protected function renderPayload(array $messages, array $options): array
    {
        // Separate system message from other messages
        $systemPrompt = null;
        $conversationMessages = [];

        foreach ($messages as $message) {
            if ($message->role === Role::System) {
                $systemPrompt = $message->getText();
            } else {
                $conversationMessages[] = $this->renderMessage($message);
            }
        }

        $payload = [
            'model' => $options['model'],
            'messages' => $conversationMessages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = array_map([$this, 'renderTool'], $options['tools']);
        }

        // Extended thinking support
        if (isset($options['thinking']) && $options['thinking']) {
            $payload['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $options['thinking_budget'] ?? 10000,
            ];
        }

        return $payload;
    }

    /**
     * Render a message for the Anthropic API.
     *
     * @return array<string, mixed>
     */
    private function renderMessage(Message $message): array
    {
        $role = match ($message->role) {
            Role::User => 'user',
            Role::Assistant => 'assistant',
            Role::Tool => 'user', // Tool results are sent as user messages
            default => 'user',
        };

        // Handle tool results
        if ($message->role === Role::Tool) {
            return [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'tool_result',
                        'tool_use_id' => $message->toolCallId,
                        'content' => $message->getText(),
                    ],
                ],
            ];
        }

        // Handle multimodal content
        if ($message->content->hasAttachments()) {
            return [
                'role' => $role,
                'content' => $this->renderMultimodalContent($message->content),
            ];
        }

        // Handle assistant messages with tool calls
        if ($message->hasToolCalls()) {
            $content = [];

            if ($message->getText() !== '') {
                $content[] = [
                    'type' => 'text',
                    'text' => $message->getText(),
                ];
            }

            foreach ($message->toolCalls as $tc) {
                $content[] = [
                    'type' => 'tool_use',
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'input' => $tc->arguments,
                ];
            }

            return [
                'role' => $role,
                'content' => $content,
            ];
        }

        return [
            'role' => $role,
            'content' => $message->getText(),
        ];
    }

    /**
     * Render multimodal content for Anthropic.
     *
     * @return array<array<string, mixed>>
     */
    private function renderMultimodalContent(Content $content): array
    {
        $parts = [];

        foreach ($content->attachments as $attachment) {
            if ($attachment->isImage()) {
                $parts[] = [
                    'type' => 'image',
                    'source' => $attachment->isUrl()
                        ? [
                            'type' => 'url',
                            'url' => $attachment->getUrl(),
                        ]
                        : [
                            'type' => 'base64',
                            'media_type' => $attachment->getMimeType(),
                            'data' => $attachment->getBase64(),
                        ],
                ];
            } elseif ($attachment->isPdf()) {
                $parts[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => $attachment->getBase64(),
                    ],
                ];
            } elseif ($attachment->isText()) {
                $text = $attachment->getContent();
                if ($text !== null) {
                    $filename = $attachment->getFilename() ?? 'file';
                    $parts[] = [
                        'type' => 'text',
                        'text' => "<file name=\"{$filename}\">\n{$text}\n</file>",
                    ];
                }
            }
        }

        // Add text content
        if ($content->text !== null && $content->text !== '') {
            $parts[] = [
                'type' => 'text',
                'text' => $content->text,
            ];
        }

        return $parts;
    }

    /**
     * Render a tool for Anthropic format.
     *
     * @param array<string, mixed> $tool
     * @return array<string, mixed>
     */
    private function renderTool(array $tool): array
    {
        return [
            'name' => $tool['function']['name'],
            'description' => $tool['function']['description'] ?? '',
            'input_schema' => $tool['function']['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
        ];
    }

    protected function parseCompletion(array $response): Message
    {
        $content = '';
        $thinking = null;
        $toolCalls = [];

        foreach ($response['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'thinking') {
                $thinking = $block['thinking'] ?? null;
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $block['input'] ?? [],
                );
            }
        }

        $tokens = null;
        if (isset($response['usage'])) {
            $tokens = Tokens::fromArray([
                'input' => $response['usage']['input_tokens'] ?? 0,
                'output' => $response['usage']['output_tokens'] ?? 0,
                'cache_creation' => $response['usage']['cache_creation_input_tokens'] ?? 0,
                'cache_read' => $response['usage']['cache_read_input_tokens'] ?? 0,
            ]);
        }

        return Message::assistant(
            text: $content,
            toolCalls: $toolCalls,
            tokens: $tokens,
            model: $response['model'] ?? null,
            stopReason: $response['stop_reason'] ?? null,
            thinking: $thinking,
        );
    }

    protected function parseStreamChunk(array $data): Chunk
    {
        $type = $data['type'] ?? '';

        // Handle different event types
        if ($type === 'content_block_delta') {
            $delta = $data['delta'] ?? [];
            $deltaType = $delta['type'] ?? '';

            if ($deltaType === 'text_delta') {
                return new Chunk(content: $delta['text'] ?? null);
            }

            if ($deltaType === 'thinking_delta') {
                return new Chunk(thinking: $delta['thinking'] ?? null);
            }

            if ($deltaType === 'input_json_delta') {
                // Tool call arguments streaming
                return new Chunk(
                    toolCalls: [
                        new ToolCall(
                            id: '',
                            name: '',
                            arguments: $delta['partial_json'] ?? '',
                        ),
                    ],
                );
            }
        }

        if ($type === 'content_block_start') {
            $block = $data['content_block'] ?? [];
            if ($block['type'] === 'tool_use') {
                return new Chunk(
                    toolCalls: [
                        new ToolCall(
                            id: $block['id'] ?? '',
                            name: $block['name'] ?? '',
                            arguments: [],
                        ),
                    ],
                );
            }
        }

        if ($type === 'message_delta') {
            $delta = $data['delta'] ?? [];
            $usage = $data['usage'] ?? null;

            return new Chunk(
                stopReason: $delta['stop_reason'] ?? null,
                tokens: $usage ? Tokens::fromArray([
                    'output' => $usage['output_tokens'] ?? 0,
                ]) : null,
                isLast: true,
            );
        }

        if ($type === 'message_stop') {
            return new Chunk(isLast: true);
        }

        return new Chunk();
    }

    public function listModels(): array
    {
        return [
            'claude-sonnet-4-20250514' => ['context' => 200000, 'vision' => true, 'tools' => true, 'thinking' => true],
            'claude-opus-4-20250514' => ['context' => 200000, 'vision' => true, 'tools' => true, 'thinking' => true],
            'claude-3-5-sonnet-20241022' => ['context' => 200000, 'vision' => true, 'tools' => true],
            'claude-3-5-haiku-20241022' => ['context' => 200000, 'vision' => true, 'tools' => true],
            'claude-3-opus-20240229' => ['context' => 200000, 'vision' => true, 'tools' => true],
        ];
    }
}
