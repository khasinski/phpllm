<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\Message;
use PHPLLM\Core\Role;
use PHPLLM\Core\Tokens;
use PHPLLM\Core\ToolCall;
use PHPLLM\Tests\TestCase;

class MessageTest extends TestCase
{
    public function testCreateSystemMessage(): void
    {
        $message = Message::system('You are helpful.');

        $this->assertEquals(Role::System, $message->role);
        $this->assertEquals('You are helpful.', $message->getText());
    }

    public function testCreateUserMessage(): void
    {
        $message = Message::user('Hello!');

        $this->assertEquals(Role::User, $message->role);
        $this->assertEquals('Hello!', $message->getText());
    }

    public function testCreateAssistantMessage(): void
    {
        $tokens = new Tokens(input: 10, output: 20);
        $message = Message::assistant(
            text: 'Hi there!',
            tokens: $tokens,
            model: 'gpt-4o',
            stopReason: 'stop',
        );

        $this->assertEquals(Role::Assistant, $message->role);
        $this->assertEquals('Hi there!', $message->getText());
        $this->assertEquals('gpt-4o', $message->model);
        $this->assertEquals('stop', $message->stopReason);
        $this->assertEquals(30, $message->tokens->total());
    }

    public function testCreateToolResultMessage(): void
    {
        $message = Message::toolResult('call_123', ['result' => 'data']);

        $this->assertEquals(Role::Tool, $message->role);
        $this->assertEquals('call_123', $message->toolCallId);
        $this->assertTrue($message->isToolResult());
    }

    public function testMessageWithToolCalls(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'get_weather', ['location' => 'Tokyo']),
        ];

        $message = Message::assistant('', toolCalls: $toolCalls);

        $this->assertTrue($message->hasToolCalls());
        $this->assertCount(1, $message->toolCalls);
        $this->assertEquals('get_weather', $message->toolCalls[0]->name);
    }

    public function testMessageWithThinking(): void
    {
        $message = Message::assistant(
            text: 'The answer is 42.',
            thinking: 'Let me think about this...',
        );

        $this->assertTrue($message->hasThinking());
        $this->assertEquals('Let me think about this...', $message->thinking);
    }

    public function testToArray(): void
    {
        $message = Message::user('Hello');
        $array = $message->toArray();

        $this->assertEquals('user', $array['role']);
        $this->assertEquals('Hello', $array['content']);
    }
}
