<?php

declare(strict_types=1);

namespace PHPLLM\Providers\OpenAI;

use PHPLLM\Core\Chunk;
use PHPLLM\Core\Content;
use PHPLLM\Core\Message;
use PHPLLM\Core\Role;
use PHPLLM\Core\Tokens;
use PHPLLM\Core\ToolCall;
use PHPLLM\Providers\BaseProvider;

/**
 * OpenAI API provider.
 *
 * Supports GPT-4, GPT-3.5, O1 models with chat, vision, tools, and streaming.
 */
class OpenAIProvider extends BaseProvider
{
    protected array $capabilities = ['chat', 'vision', 'tools', 'streaming', 'json_mode'];

    public function getSlug(): string
    {
        return 'openai';
    }

    public function getApiBase(): string
    {
        return $this->config->getOpenaiApiBase();
    }

    protected function getApiKey(): ?string
    {
        return $this->config->getOpenaiApiKey();
    }

    protected function renderPayload(array $messages, array $options): array
    {
        $payload = [
            'model' => $options['model'],
            'messages' => array_map([$this, 'renderMessage'], $messages),
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }

        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        return $payload;
    }

    /**
     * Render a message for the OpenAI API.
     *
     * @return array<string, mixed>
     */
    private function renderMessage(Message $message): array
    {
        $data = [
            'role' => $message->role->value,
        ];

        // Handle tool results
        if ($message->role === Role::Tool) {
            $data['tool_call_id'] = $message->toolCallId;
            $data['content'] = $message->getText();
            return $data;
        }

        // Handle multimodal content
        if ($message->content->hasAttachments()) {
            $data['content'] = $this->renderMultimodalContent($message->content);
        } else {
            $data['content'] = $message->getText();
        }

        // Handle tool calls in assistant messages
        if ($message->hasToolCalls()) {
            $data['tool_calls'] = array_map(function (ToolCall $tc) {
                return [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $tc->name,
                        'arguments' => json_encode($tc->arguments),
                    ],
                ];
            }, $message->toolCalls);
        }

        return $data;
    }

    /**
     * Render multimodal content (text + images).
     *
     * @return array<array<string, mixed>>
     */
    private function renderMultimodalContent(Content $content): array
    {
        $parts = [];

        // Add text first
        if ($content->text !== null && $content->text !== '') {
            $parts[] = [
                'type' => 'text',
                'text' => $content->text,
            ];
        }

        // Add attachments
        foreach ($content->attachments as $attachment) {
            if ($attachment->isImage()) {
                if ($attachment->isUrl()) {
                    $parts[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $attachment->getUrl(),
                        ],
                    ];
                } else {
                    $parts[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $attachment->getDataUrl(),
                        ],
                    ];
                }
            } elseif ($attachment->isText()) {
                // Embed text files as text content
                $text = $attachment->getContent();
                if ($text !== null) {
                    $filename = $attachment->getFilename() ?? 'file';
                    $parts[] = [
                        'type' => 'text',
                        'text' => "<file name=\"{$filename}\">\n{$text}\n</file>",
                    ];
                }
            }
            // PDFs and other files would need special handling
            // OpenAI doesn't natively support PDFs in vision
        }

        return $parts;
    }

    protected function parseCompletion(array $response): Message
    {
        $choice = $response['choices'][0] ?? [];
        $messageData = $choice['message'] ?? [];

        $toolCalls = [];
        if (isset($messageData['tool_calls'])) {
            foreach ($messageData['tool_calls'] as $tc) {
                $arguments = $tc['function']['arguments'] ?? '{}';
                $toolCalls[] = new ToolCall(
                    id: $tc['id'],
                    name: $tc['function']['name'],
                    arguments: json_decode($arguments, true) ?? [],
                );
            }
        }

        $tokens = null;
        if (isset($response['usage'])) {
            $tokens = Tokens::fromArray($response['usage']);
        }

        return Message::assistant(
            text: $messageData['content'] ?? '',
            toolCalls: $toolCalls,
            tokens: $tokens,
            model: $response['model'] ?? null,
            stopReason: $choice['finish_reason'] ?? null,
        );
    }

    protected function parseStreamChunk(array $data): Chunk
    {
        $choice = $data['choices'][0] ?? [];
        $delta = $choice['delta'] ?? [];

        $toolCalls = [];
        if (isset($delta['tool_calls'])) {
            foreach ($delta['tool_calls'] as $tc) {
                $toolCalls[] = new ToolCall(
                    id: $tc['id'] ?? '',
                    name: $tc['function']['name'] ?? '',
                    arguments: $tc['function']['arguments'] ?? '',
                );
            }
        }

        $tokens = null;
        if (isset($data['usage'])) {
            $tokens = Tokens::fromArray($data['usage']);
        }

        return new Chunk(
            content: $delta['content'] ?? null,
            toolCalls: $toolCalls,
            stopReason: $choice['finish_reason'] ?? null,
            tokens: $tokens,
        );
    }

    public function listModels(): array
    {
        return [
            'gpt-4o' => ['context' => 128000, 'vision' => true, 'tools' => true],
            'gpt-4o-mini' => ['context' => 128000, 'vision' => true, 'tools' => true],
            'gpt-4-turbo' => ['context' => 128000, 'vision' => true, 'tools' => true],
            'gpt-4' => ['context' => 8192, 'vision' => false, 'tools' => true],
            'gpt-3.5-turbo' => ['context' => 16385, 'vision' => false, 'tools' => true],
            'o1' => ['context' => 200000, 'vision' => true, 'tools' => false],
            'o1-mini' => ['context' => 128000, 'vision' => false, 'tools' => false],
            'o1-preview' => ['context' => 128000, 'vision' => false, 'tools' => false],
            'o3-mini' => ['context' => 200000, 'vision' => true, 'tools' => true],
        ];
    }
}
