<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Integration;

use PHPLLM\Core\Embedding;
use PHPLLM\Core\Tool;
use PHPLLM\PHPLLM;
use PHPLLM\Tests\VCRTestCase;

class GetWeather extends Tool
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
        return ['temperature' => 22, 'condition' => 'sunny'];
    }
}

class OpenAITest extends VCRTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireOpenAI();
    }

    public function testBasicChat(): void
    {
        $this->useCassette('openai_basic_chat');

        $chat = PHPLLM::chat('gpt-4o-mini');
        $response = $chat->ask('Say "hello" and nothing else.');

        $this->assertStringContainsStringIgnoringCase('hello', $response->getText());
        $this->assertNotNull($response->tokens);
    }

    public function testChatWithInstructions(): void
    {
        $this->useCassette('openai_chat_instructions');

        $chat = PHPLLM::chat('gpt-4o-mini')
            ->withInstructions('Always respond in exactly 3 words.')
            ->withTemperature(0);

        $response = $chat->ask('How are you?');

        // Should be roughly 3 words
        $words = str_word_count($response->getText());
        $this->assertLessThanOrEqual(5, $words);
    }

    public function testMultiTurnConversation(): void
    {
        $this->useCassette('openai_multi_turn');

        $chat = PHPLLM::chat('gpt-4o-mini');

        $chat->ask('My name is Alice.');
        $response = $chat->ask('What is my name?');

        $this->assertStringContainsStringIgnoringCase('Alice', $response->getText());
    }

    public function testToolCalling(): void
    {
        $this->useCassette('openai_tool_calling');

        $chat = PHPLLM::chat('gpt-4o-mini')
            ->withTool(GetWeather::class);

        $response = $chat->ask('What is the weather in Tokyo?');

        // The response should mention the weather data
        $text = strtolower($response->getText());
        $this->assertTrue(
            str_contains($text, 'sunny') ||
            str_contains($text, '22') ||
            str_contains($text, 'weather'),
        );
    }

    public function testEmbeddings(): void
    {
        $this->useCassette('openai_embeddings');

        $embedding = PHPLLM::embed('Hello, world!');

        $this->assertInstanceOf(Embedding::class, $embedding);
        $this->assertGreaterThan(100, $embedding->dimensions);
        $this->assertCount($embedding->dimensions, $embedding->vector);
    }

    public function testMultipleEmbeddings(): void
    {
        $this->useCassette('openai_multiple_embeddings');

        $embeddings = PHPLLM::embed(['Hello', 'World', 'Test']);

        $this->assertIsArray($embeddings);
        $this->assertCount(3, $embeddings);
        $this->assertInstanceOf(Embedding::class, $embeddings[0]);
    }

    public function testEmbeddingSimilarity(): void
    {
        $this->useCassette('openai_embedding_similarity');

        $embeddings = PHPLLM::embed([
            'The cat sat on the mat',
            'A feline rested on the rug',
            'JavaScript is a programming language',
        ]);

        // Similar sentences should have higher similarity
        $sim1 = $embeddings[0]->similarity($embeddings[1]); // cat/feline
        $sim2 = $embeddings[0]->similarity($embeddings[2]); // cat/javascript

        $this->assertGreaterThan($sim2, $sim1);
    }

    /**
     * @group skip-vcr
     */
    public function testStreaming(): void
    {
        // VCR doesn't handle streaming responses well, skip when using cassettes
        if (static::$vcrEnabled && !getenv('OPENAI_API_KEY')) {
            $this->markTestSkipped('Streaming tests require live API (VCR incompatible)');
        }

        $this->useCassette('openai_streaming');

        $chunks = [];
        $chat = PHPLLM::chat('gpt-4o-mini');

        $response = $chat->ask(
            'Count from 1 to 3.',
            stream: function ($chunk) use (&$chunks) {
                $chunks[] = $chunk;
            },
        );

        $this->assertNotEmpty($chunks);
        $this->assertNotEmpty($response->getText());
    }
}
