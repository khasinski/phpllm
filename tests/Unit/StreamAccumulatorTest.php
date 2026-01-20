<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\Chunk;
use PHPLLM\Core\StreamAccumulator;
use PHPLLM\Core\ToolCall;
use PHPLLM\Exceptions\StreamException;
use PHPLLM\Tests\TestCase;

class StreamAccumulatorTest extends TestCase
{
    public function testAccumulatesContent(): void
    {
        $accumulator = new StreamAccumulator();

        $accumulator->add(new Chunk(content: 'Hello '));
        $accumulator->add(new Chunk(content: 'World'));

        $this->assertEquals('Hello World', $accumulator->getContent());
    }

    public function testAccumulatesThinking(): void
    {
        $accumulator = new StreamAccumulator();

        $accumulator->add(new Chunk(thinking: 'Let me think...'));
        $accumulator->add(new Chunk(thinking: ' about this.'));

        $this->assertEquals('Let me think... about this.', $accumulator->getThinking());
    }

    public function testAccumulatesToolCalls(): void
    {
        $accumulator = new StreamAccumulator();

        $accumulator->add(new Chunk(toolCalls: [
            new ToolCall('call_1', 'get_weather', ['city' => 'London']),
        ]));

        $message = $accumulator->toMessage();
        $this->assertCount(1, $message->toolCalls);
        $this->assertEquals('get_weather', $message->toolCalls[0]->name);
    }

    public function testContentSizeLimit(): void
    {
        $accumulator = new StreamAccumulator(maxContentSize: 100);

        // Add content up to limit
        $accumulator->add(new Chunk(content: str_repeat('a', 50)));

        // This should exceed the limit
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('exceeded maximum size');
        $accumulator->add(new Chunk(content: str_repeat('b', 51)));
    }

    public function testThinkingSizeLimit(): void
    {
        $accumulator = new StreamAccumulator(maxContentSize: 100);

        $accumulator->add(new Chunk(thinking: str_repeat('a', 50)));

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('thinking content exceeded');
        $accumulator->add(new Chunk(thinking: str_repeat('b', 51)));
    }

    public function testToolCallsLimit(): void
    {
        $accumulator = new StreamAccumulator(maxToolCalls: 2);

        // Add two tool calls
        $accumulator->add(new Chunk(toolCalls: [
            new ToolCall('call_1', 'tool1', []),
        ]));
        $accumulator->add(new Chunk(toolCalls: [
            new ToolCall('call_2', 'tool2', []),
        ]));

        // Third should fail
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('exceeded maximum');
        $accumulator->add(new Chunk(toolCalls: [
            new ToolCall('call_3', 'tool3', []),
        ]));
    }

    public function testToolArgumentsSizeLimit(): void
    {
        $accumulator = new StreamAccumulator(maxArgumentsSize: 100);

        // Start a tool call
        $accumulator->add(new Chunk(toolCalls: [
            new ToolCall('call_1', 'tool1', str_repeat('a', 50)),
        ]));

        // Add more arguments that exceed limit
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Tool arguments exceeded');
        $accumulator->add(new Chunk(toolCalls: [
            new ToolCall('call_1', 'tool1', str_repeat('b', 51)),
        ]));
    }

    public function testConvertsToMessage(): void
    {
        $accumulator = new StreamAccumulator();

        $accumulator->add(new Chunk(content: 'Hello'));
        $accumulator->add(new Chunk(stopReason: 'stop'));

        $message = $accumulator->toMessage('gpt-4');

        $this->assertEquals('Hello', $message->getText());
        $this->assertEquals('gpt-4', $message->model);
        $this->assertEquals('stop', $message->stopReason);
    }
}
