<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Contracts\ProviderInterface;
use PHPLLM\Core\Attachment;
use PHPLLM\Core\Chat;
use PHPLLM\Core\Content;
use PHPLLM\Core\Message;
use PHPLLM\Core\Role;
use PHPLLM\Core\Tokens;
use PHPLLM\Core\Tool;
use PHPLLM\Core\ToolCall;
use PHPLLM\Exceptions\ToolExecutionException;
use PHPLLM\Tests\TestCase;

class ChatTest extends TestCase
{
    private string $testImagePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test image
        $this->testImagePath = sys_get_temp_dir() . '/chat_test_image.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($this->testImagePath, $png);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->testImagePath)) {
            unlink($this->testImagePath);
        }
    }

    private function createMockProvider(Message $response): ProviderInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')->willReturn($response);
        return $provider;
    }

    public function testAskWithImageNamedArg(): void
    {
        $response = Message::assistant('I see an image');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->ask('What is this?', image: $this->testImagePath);

        $this->assertEquals('I see an image', $result->getText());

        // Check the user message has the attachment
        $messages = $chat->getMessages();
        $userMessage = $messages[0];
        $this->assertTrue($userMessage->content->hasAttachments());
        $this->assertCount(1, $userMessage->content->getImages());
    }

    public function testAskWithMultipleImages(): void
    {
        $response = Message::assistant('I see multiple images');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->ask('Compare these', image: [$this->testImagePath, $this->testImagePath]);

        $messages = $chat->getMessages();
        $userMessage = $messages[0];
        $this->assertCount(2, $userMessage->content->getImages());
    }

    public function testAskWithAttachmentObject(): void
    {
        $response = Message::assistant('Got it');
        $provider = $this->createMockProvider($response);

        $attachment = Attachment::image($this->testImagePath);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->ask('Describe', image: $attachment);

        $messages = $chat->getMessages();
        $this->assertTrue($messages[0]->content->hasAttachments());
    }

    public function testAskWithImageUrl(): void
    {
        $response = Message::assistant('URL image');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->ask('What is this?', image: 'https://example.com/photo.jpg');

        $messages = $chat->getMessages();
        $attachment = $messages[0]->content->attachments[0];
        $this->assertTrue($attachment->isUrl());
    }

    public function testAskWithFileNamedArg(): void
    {
        $response = Message::assistant('File received');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->ask('Process this', file: $this->testImagePath);

        $messages = $chat->getMessages();
        $this->assertTrue($messages[0]->content->hasAttachments());
    }

    public function testAskWithNoAttachments(): void
    {
        $response = Message::assistant('Hello');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->ask('Hi there');

        $messages = $chat->getMessages();
        $this->assertFalse($messages[0]->content->hasAttachments());
    }

    public function testWithInstructions(): void
    {
        $response = Message::assistant('Done');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $chat->withInstructions('You are helpful');
        $chat->ask('Hi');

        // Instructions are added when building messages for API, not stored in messages array
        $this->assertCount(2, $chat->getMessages()); // user + assistant
    }

    public function testWithTemperature(): void
    {
        $response = Message::assistant('Done');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->withTemperature(0.5);

        $this->assertSame($chat, $result); // Fluent interface
    }

    public function testWithTemperatureValidation(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $chat = new Chat($provider, 'gpt-4o');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 2.0');

        $chat->withTemperature(2.5);
    }

    public function testWithMaxTokens(): void
    {
        $response = Message::assistant('Done');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->withMaxTokens(100);

        $this->assertSame($chat, $result);
    }

    public function testWithMaxTokensValidation(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $chat = new Chat($provider, 'gpt-4o');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be at least 1');

        $chat->withMaxTokens(0);
    }

    public function testWithHeaders(): void
    {
        $response = Message::assistant('Done');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->withHeaders(['X-Custom' => 'value']);

        $this->assertSame($chat, $result);
    }

    public function testClear(): void
    {
        $response = Message::assistant('Response');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $chat->ask('First message');

        $this->assertCount(2, $chat->getMessages());

        $chat->clear();

        $this->assertCount(0, $chat->getMessages());
    }

    public function testGetLastMessage(): void
    {
        $response = Message::assistant('Last');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $chat->ask('Hello');

        $last = $chat->getLastMessage();
        $this->assertEquals('Last', $last->getText());
    }

    public function testGetLastMessageWhenEmpty(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $chat = new Chat($provider, 'gpt-4o');

        $this->assertNull($chat->getLastMessage());
    }

    public function testOnNewMessageCallback(): void
    {
        $response = Message::assistant('Response');
        $provider = $this->createMockProvider($response);

        $receivedMessages = [];
        $chat = new Chat($provider, 'gpt-4o');
        $chat->onNewMessage(function (Message $msg) use (&$receivedMessages) {
            $receivedMessages[] = $msg;
        });

        $chat->ask('Hello');

        $this->assertCount(2, $receivedMessages);
        $this->assertEquals(Role::User, $receivedMessages[0]->role);
        $this->assertEquals(Role::Assistant, $receivedMessages[1]->role);
    }

    public function testWithToolAsClass(): void
    {
        $response = Message::assistant('Done');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->withTool(TestWeatherTool::class);

        $this->assertSame($chat, $result);
    }

    public function testWithToolAsInstance(): void
    {
        $response = Message::assistant('Done');
        $provider = $this->createMockProvider($response);

        $tool = new TestWeatherTool();
        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->withTool($tool);

        $this->assertSame($chat, $result);
    }

    public function testWithTools(): void
    {
        $response = Message::assistant('Done');
        $provider = $this->createMockProvider($response);

        $chat = new Chat($provider, 'gpt-4o');
        $result = $chat->withTools([TestWeatherTool::class, new TestWeatherTool()]);

        $this->assertSame($chat, $result);
    }
}

/**
 * Test tool for unit tests.
 */
class TestWeatherTool extends Tool
{
    protected string $name = 'get_weather';
    protected string $description = 'Get weather for a location';

    protected function parameters(): array
    {
        return [
            'location' => [
                'type' => 'string',
                'description' => 'City name',
                'required' => true,
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        return ['temperature' => 20, 'unit' => 'celsius'];
    }
}
