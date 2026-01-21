<?php

declare(strict_types=1);

namespace PHPLLM\Providers\Gemini;

use PHPLLM\Core\Chunk;
use PHPLLM\Core\Content;
use PHPLLM\Core\Message;
use PHPLLM\Core\Role;
use PHPLLM\Core\Tokens;
use PHPLLM\Core\ToolCall;
use PHPLLM\Providers\BaseProvider;

/**
 * Google Gemini API provider.
 *
 * Supports Gemini 2.0, 1.5 models with chat, vision, tools, and streaming.
 */
class GeminiProvider extends BaseProvider
{
    protected array $capabilities = ['chat', 'vision', 'tools', 'streaming'];

    public function getSlug(): string
    {
        return 'gemini';
    }

    public function getApiBase(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    protected function getApiKey(): ?string
    {
        return $this->config->getGeminiApiKey();
    }

    public function getHeaders(): array
    {
        // Gemini uses API key in URL, not headers
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function getCompletionEndpoint(): string
    {
        return '/models/{model}:generateContent';
    }

    public function complete(array $messages, array $options = []): Message
    {
        $model = $options['model'] ?? 'gemini-2.0-flash';
        $endpoint = str_replace('{model}', $model, $this->getCompletionEndpoint());
        $url = $this->getApiBase() . $endpoint . '?key=' . $this->getApiKey();

        $payload = $this->renderPayload($messages, $options);

        $response = $this->connection->post($url, $this->getHeaders(), $payload);

        return $this->parseCompletion($response);
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $model = $options['model'] ?? 'gemini-2.0-flash';
        $endpoint = str_replace('{model}', $model, '/models/{model}:streamGenerateContent');
        $url = $this->getApiBase() . $endpoint . '?key=' . $this->getApiKey() . '&alt=sse';

        $payload = $this->renderPayload($messages, $options);

        foreach ($this->connection->stream($url, $this->getHeaders(), $payload) as $data) {
            $decoded = json_decode($data, true);
            if ($decoded !== null) {
                yield $this->parseStreamChunk($decoded);
            }
        }
    }

    protected function renderPayload(array $messages, array $options): array
    {
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $message) {
            if ($message->role === Role::System) {
                $systemInstruction = $message->getText();
                continue;
            }

            $contents[] = $this->renderMessage($message);
        }

        $payload = [
            'contents' => $contents,
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        // Generation config
        $generationConfig = [];
        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = $options['max_tokens'];
        }
        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        // Tools
        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = [
                ['functionDeclarations' => array_map([$this, 'renderTool'], $options['tools'])],
            ];
        }

        return $payload;
    }

    /**
     * Render a message for the Gemini API.
     *
     * @return array<string, mixed>
     */
    private function renderMessage(Message $message): array
    {
        $role = match ($message->role) {
            Role::User => 'user',
            Role::Assistant => 'model',
            Role::Tool => 'function',
            default => 'user',
        };

        $parts = [];

        // Handle tool results
        if ($message->role === Role::Tool) {
            return [
                'role' => 'function',
                'parts' => [
                    [
                        'functionResponse' => [
                            'name' => $message->toolCallId ?? 'unknown',
                            'response' => ['result' => $message->getText()],
                        ],
                    ],
                ],
            ];
        }

        // Handle multimodal content
        if ($message->content->hasAttachments()) {
            $parts = $this->renderMultimodalContent($message->content);
        } else {
            $parts[] = ['text' => $message->getText()];
        }

        // Handle tool calls in assistant messages
        if ($message->hasToolCalls()) {
            foreach ($message->toolCalls as $tc) {
                $parts[] = [
                    'functionCall' => [
                        'name' => $tc->name,
                        'args' => $tc->arguments,
                    ],
                ];
            }
        }

        return [
            'role' => $role,
            'parts' => $parts,
        ];
    }

    /**
     * Render multimodal content for Gemini.
     *
     * @return array<array<string, mixed>>
     */
    private function renderMultimodalContent(Content $content): array
    {
        $parts = [];

        // Add text first
        if ($content->text !== null && $content->text !== '') {
            $parts[] = ['text' => $content->text];
        }

        // Add attachments
        foreach ($content->attachments as $attachment) {
            if ($attachment->isImage() || $attachment->isPdf()) {
                if ($attachment->isUrl()) {
                    // Gemini requires inline data, fetch the URL content
                    $imageData = $this->fetchUrlContent($attachment->getUrl());
                    if ($imageData !== null) {
                        $parts[] = [
                            'inlineData' => [
                                'mimeType' => $attachment->getMimeType(),
                                'data' => base64_encode($imageData),
                            ],
                        ];
                    }
                } else {
                    $parts[] = [
                        'inlineData' => [
                            'mimeType' => $attachment->getMimeType(),
                            'data' => $attachment->getBase64(),
                        ],
                    ];
                }
            }
        }

        return $parts;
    }

    /**
     * Fetch content from a URL.
     */
    private function fetchUrlContent(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'PHPLLM/1.0',
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        return $content !== false ? $content : null;
    }

    /**
     * Render a tool for Gemini format.
     *
     * @param array<string, mixed> $tool
     * @return array<string, mixed>
     */
    private function renderTool(array $tool): array
    {
        $function = $tool['function'] ?? $tool;

        return [
            'name' => $function['name'],
            'description' => $function['description'] ?? '',
            'parameters' => $function['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
        ];
    }

    protected function parseCompletion(array $response): Message
    {
        $candidate = $response['candidates'][0] ?? [];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $text = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    id: $part['functionCall']['name'] . '_' . uniqid(),
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                );
            }
        }

        $tokens = null;
        if (isset($response['usageMetadata'])) {
            $tokens = Tokens::fromArray([
                'input' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'output' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
            ]);
        }

        return Message::assistant(
            text: $text,
            toolCalls: $toolCalls,
            tokens: $tokens,
            model: $response['modelVersion'] ?? null,
            stopReason: $candidate['finishReason'] ?? null,
        );
    }

    protected function parseStreamChunk(array $data): Chunk
    {
        $candidate = $data['candidates'][0] ?? [];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $text = null;
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text = ($text ?? '') . $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    id: $part['functionCall']['name'] . '_' . uniqid(),
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                );
            }
        }

        $tokens = null;
        if (isset($data['usageMetadata'])) {
            $tokens = Tokens::fromArray([
                'input' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'output' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            ]);
        }

        return new Chunk(
            content: $text,
            toolCalls: $toolCalls,
            stopReason: $candidate['finishReason'] ?? null,
            tokens: $tokens,
            isLast: isset($candidate['finishReason']),
        );
    }

    public function listModels(): array
    {
        return [
            // Gemini 2.5 Series (Latest)
            'gemini-2.5-flash' => ['context' => 1048576, 'vision' => true, 'tools' => true, 'thinking' => true],
            'gemini-2.5-pro' => ['context' => 1048576, 'vision' => true, 'tools' => true, 'thinking' => true],

            // Gemini 2.0 Series
            'gemini-2.0-flash' => ['context' => 1000000, 'vision' => true, 'tools' => true],
            'gemini-2.0-flash-lite' => ['context' => 1000000, 'vision' => true, 'tools' => true],
            'gemini-2.0-pro' => ['context' => 1000000, 'vision' => true, 'tools' => true],

            // Gemini 1.5 Series (Legacy)
            'gemini-1.5-pro' => ['context' => 2000000, 'vision' => true, 'tools' => true],
            'gemini-1.5-flash' => ['context' => 1000000, 'vision' => true, 'tools' => true],
            'gemini-1.5-flash-8b' => ['context' => 1000000, 'vision' => true, 'tools' => true],
        ];
    }
}
