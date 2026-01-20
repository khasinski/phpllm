<?php

declare(strict_types=1);

namespace PHPLLM\Providers\OpenAI;

use PHPLLM\Contracts\EmbeddingInterface;
use PHPLLM\Contracts\ImageGenerationInterface;
use PHPLLM\Core\Chunk;
use PHPLLM\Core\Content;
use PHPLLM\Core\Embedding;
use PHPLLM\Core\Image;
use PHPLLM\Core\Message;
use PHPLLM\Core\Role;
use PHPLLM\Core\Tokens;
use PHPLLM\Core\ToolCall;
use PHPLLM\Providers\BaseProvider;

/**
 * OpenAI API provider.
 *
 * Supports GPT-4, GPT-3.5, O1 models with chat, vision, tools, streaming,
 * embeddings, and image generation.
 */
class OpenAIProvider extends BaseProvider implements EmbeddingInterface, ImageGenerationInterface
{
    protected array $capabilities = [
        'chat', 'vision', 'tools', 'streaming', 'json_mode', 'embeddings', 'images',
    ];

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

    // =========================================================================
    // Embeddings
    // =========================================================================

    public function embed(string|array $input, array $options = []): Embedding|array
    {
        $url = $this->getApiBase() . '/embeddings';
        $model = $options['model'] ?? 'text-embedding-3-small';

        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        if (isset($options['dimensions'])) {
            $payload['dimensions'] = $options['dimensions'];
        }

        $response = $this->connection->post($url, $this->getHeaders(), $payload);

        $tokens = null;
        if (isset($response['usage'])) {
            $tokens = Tokens::fromArray($response['usage']);
        }

        // Handle multiple inputs
        if (is_array($input)) {
            return array_map(function ($data) use ($model, $tokens) {
                return new Embedding(
                    vector: $data['embedding'],
                    dimensions: count($data['embedding']),
                    model: $model,
                    tokens: $tokens,
                );
            }, $response['data']);
        }

        // Single input
        $data = $response['data'][0];
        return new Embedding(
            vector: $data['embedding'],
            dimensions: count($data['embedding']),
            model: $model,
            tokens: $tokens,
        );
    }

    // =========================================================================
    // Image Generation
    // =========================================================================

    public function generateImage(string $prompt, array $options = []): Image
    {
        $images = $this->generateImages($prompt, 1, $options);
        return $images[0];
    }

    public function generateImages(string $prompt, int $count, array $options = []): array
    {
        $url = $this->getApiBase() . '/images/generations';
        $model = $options['model'] ?? 'gpt-image-1.5';

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => $count,
            'size' => $options['size'] ?? '1024x1024',
            'response_format' => $options['response_format'] ?? 'url',
        ];

        if (isset($options['quality'])) {
            $payload['quality'] = $options['quality'];
        }

        if (isset($options['style'])) {
            $payload['style'] = $options['style'];
        }

        $response = $this->connection->post($url, $this->getHeaders(), $payload);

        return array_map(function ($data) use ($model) {
            return new Image(
                url: $data['url'] ?? null,
                base64: $data['b64_json'] ?? null,
                revisedPrompt: $data['revised_prompt'] ?? null,
                model: $model,
            );
        }, $response['data']);
    }

    // =========================================================================
    // Chat Completions
    // =========================================================================

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
            // GPT-5.2 Series (Latest - December 2025)
            'gpt-5.2' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],
            'gpt-5.2-pro' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],
            'gpt-5.2-chat-latest' => ['context' => 200000, 'vision' => true, 'tools' => true],
            'gpt-5.2-codex' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true, 'coding' => true],

            // GPT-5.1 Series
            'gpt-5.1' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],

            // GPT-5 (Original)
            'gpt-5' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],

            // GPT-4.1 Series - 1M context, improved coding
            'gpt-4.1' => ['context' => 1000000, 'vision' => true, 'tools' => true],
            'gpt-4.1-mini' => ['context' => 1000000, 'vision' => true, 'tools' => true],
            'gpt-4.1-nano' => ['context' => 1000000, 'vision' => true, 'tools' => true],

            // Reasoning Models (o-series)
            'o3' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],
            'o3-pro' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],
            'o3-mini' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],
            'o4-mini' => ['context' => 200000, 'vision' => true, 'tools' => true, 'reasoning' => true],

            // GPT-4o Series (Legacy)
            'gpt-4o' => ['context' => 128000, 'vision' => true, 'tools' => true, 'legacy' => true],
            'gpt-4o-mini' => ['context' => 128000, 'vision' => true, 'tools' => true, 'legacy' => true],
            'gpt-4o-audio-preview' => ['context' => 128000, 'vision' => true, 'tools' => true, 'audio' => true],

            // Embeddings
            'text-embedding-3-small' => ['dimensions' => 1536, 'type' => 'embedding'],
            'text-embedding-3-large' => ['dimensions' => 3072, 'type' => 'embedding'],

            // Image Generation
            'gpt-image-1.5' => ['type' => 'image'],
            'dall-e-3' => ['type' => 'image', 'legacy' => true],
            'dall-e-2' => ['type' => 'image', 'legacy' => true],
        ];
    }
}
