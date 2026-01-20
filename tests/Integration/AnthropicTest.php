<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Integration;

use PHPLLM\PHPLLM;
use PHPLLM\Tests\VCRTestCase;

class AnthropicTest extends VCRTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireAnthropic();
    }

    public function testBasicChat(): void
    {
        $this->useCassette('anthropic_basic_chat');

        $chat = PHPLLM::chat('claude-3-5-haiku-20241022');
        $response = $chat->ask('Say "hello" and nothing else.');

        $this->assertStringContainsStringIgnoringCase('hello', $response->getText());
    }

    public function testChatWithInstructions(): void
    {
        $this->useCassette('anthropic_chat_instructions');

        $chat = PHPLLM::chat('claude-3-5-haiku-20241022')
            ->withInstructions('You are a pirate. Always respond like a pirate.')
            ->withTemperature(0.7);

        $response = $chat->ask('Hello!');

        // Should have pirate-like language
        $text = strtolower($response->getText());
        $this->assertTrue(
            str_contains($text, 'ahoy') ||
            str_contains($text, 'arr') ||
            str_contains($text, 'matey') ||
            str_contains($text, 'ye')
        );
    }

    public function testMultiTurnConversation(): void
    {
        $this->useCassette('anthropic_multi_turn');

        $chat = PHPLLM::chat('claude-3-5-haiku-20241022');

        $chat->ask('Remember the number 42.');
        $response = $chat->ask('What number did I ask you to remember?');

        $this->assertStringContainsString('42', $response->getText());
    }

    public function testTokenUsage(): void
    {
        $this->useCassette('anthropic_token_usage');

        $chat = PHPLLM::chat('claude-3-5-haiku-20241022');
        $response = $chat->ask('Hi!');

        $this->assertNotNull($response->tokens);
        $this->assertGreaterThan(0, $response->tokens->input);
        $this->assertGreaterThan(0, $response->tokens->output);
    }
}
