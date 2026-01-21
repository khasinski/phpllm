<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\ToolCall;
use PHPLLM\Exceptions\ToolExecutionException;
use PHPLLM\Tests\TestCase;

class ToolExecutionExceptionTest extends TestCase
{
    public function testFromException(): void
    {
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'get_weather',
            arguments: ['location' => 'Paris'],
        );

        $original = new \RuntimeException('API unavailable', 503);

        $exception = ToolExecutionException::fromException($original, $toolCall);

        $this->assertStringContainsString('get_weather', $exception->getMessage());
        $this->assertStringContainsString('API unavailable', $exception->getMessage());
        $this->assertEquals('get_weather', $exception->toolName);
        $this->assertSame($toolCall, $exception->toolCall);
        $this->assertEquals(['location' => 'Paris'], $exception->arguments);
        $this->assertEquals(503, $exception->getCode());
        $this->assertSame($original, $exception->getPrevious());
    }

    public function testUnknownTool(): void
    {
        $toolCall = new ToolCall(
            id: 'call_456',
            name: 'unknown_tool',
            arguments: ['foo' => 'bar'],
        );

        $exception = ToolExecutionException::unknownTool($toolCall, ['get_weather', 'search']);

        $this->assertStringContainsString('Unknown tool', $exception->getMessage());
        $this->assertStringContainsString('unknown_tool', $exception->getMessage());
        $this->assertStringContainsString('get_weather, search', $exception->getMessage());
        $this->assertEquals('unknown_tool', $exception->toolName);
        $this->assertSame($toolCall, $exception->toolCall);
    }

    public function testUnknownToolWithoutAvailableTools(): void
    {
        $toolCall = new ToolCall(
            id: 'call_789',
            name: 'missing',
            arguments: [],
        );

        $exception = ToolExecutionException::unknownTool($toolCall);

        $this->assertStringContainsString('Unknown tool', $exception->getMessage());
        $this->assertStringNotContainsString('Available tools', $exception->getMessage());
    }

    public function testDirectConstruction(): void
    {
        $toolCall = new ToolCall(
            id: 'call_abc',
            name: 'my_tool',
            arguments: ['arg' => 'value'],
        );

        $exception = new ToolExecutionException(
            message: 'Custom error message',
            toolName: 'my_tool',
            toolCall: $toolCall,
            arguments: ['arg' => 'value'],
            code: 42,
        );

        $this->assertEquals('Custom error message', $exception->getMessage());
        $this->assertEquals('my_tool', $exception->toolName);
        $this->assertEquals(42, $exception->getCode());
    }
}
