<?php

/**
 * PHPLLM Basic Usage Examples
 *
 * Run with: php examples/basic.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPLLM\PHPLLM;
use PHPLLM\Core\Tool;

// Configure with your API keys
PHPLLM::configure([
    'openai_api_key' => getenv('OPENAI_API_KEY'),
    'anthropic_api_key' => getenv('ANTHROPIC_API_KEY'),
]);

// =============================================================================
// Basic Chat
// =============================================================================

echo "=== Basic Chat ===\n";

$chat = PHPLLM::chat(); // Uses default model (gpt-4o-mini)
$response = $chat->ask('What is PHP in one sentence?');

echo "Response: " . $response->getText() . "\n\n";

// =============================================================================
// With Instructions
// =============================================================================

echo "=== With Instructions ===\n";

$chat = PHPLLM::chat('gpt-4o')
    ->withInstructions('You are a helpful PHP expert. Be concise.')
    ->withTemperature(0.7);

$response = $chat->ask('What are traits in PHP?');
echo "Response: " . $response->getText() . "\n\n";

// =============================================================================
// Multi-turn Conversation
// =============================================================================

echo "=== Multi-turn Conversation ===\n";

$chat = PHPLLM::chat();
$chat->withInstructions('You are a helpful assistant.');

$chat->ask('My name is Chris.');
$response = $chat->ask('What is my name?');

echo "Response: " . $response->getText() . "\n\n";

// =============================================================================
// Using Claude
// =============================================================================

echo "=== Using Claude ===\n";

$chat = PHPLLM::chat('claude-3-5-haiku-20241022');
$response = $chat->ask('Say hello in PHP style!');

echo "Response: " . $response->getText() . "\n\n";

// =============================================================================
// With Image (Vision)
// =============================================================================

echo "=== Vision Example ===\n";

$chat = PHPLLM::chat('gpt-4o');
// $response = $chat->ask(
//     'What is in this image?',
//     with: 'path/to/image.jpg'  // Local file
// );

// Or with URL:
// $response = $chat->ask(
//     'What is in this image?',
//     with: 'https://example.com/image.jpg'
// );

echo "(Skipped - uncomment and provide an image path to test)\n\n";

// =============================================================================
// Streaming
// =============================================================================

echo "=== Streaming ===\n";

$chat = PHPLLM::chat();
$response = $chat->ask(
    'Count from 1 to 5 slowly.',
    stream: function ($chunk) {
        if ($chunk->hasContent()) {
            echo $chunk->content;
            flush();
        }
    }
);

echo "\n\n";

// =============================================================================
// Tool/Function Calling
// =============================================================================

echo "=== Tool Calling ===\n";

// Define a tool
class GetWeather extends Tool
{
    protected string $name = 'get_weather';
    protected string $description = 'Get the current weather for a location';

    protected function parameters(): array
    {
        return [
            'location' => [
                'type' => 'string',
                'description' => 'City name, e.g. "San Francisco"',
                'required' => true,
            ],
            'unit' => [
                'type' => 'string',
                'enum' => ['celsius', 'fahrenheit'],
                'default' => 'celsius',
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $location = $arguments['location'];
        $unit = $arguments['unit'] ?? 'celsius';

        // In a real app, you'd call a weather API
        return [
            'location' => $location,
            'temperature' => 22,
            'unit' => $unit,
            'condition' => 'sunny',
        ];
    }
}

$chat = PHPLLM::chat('gpt-4o')
    ->withTool(GetWeather::class)
    ->onToolCall(function ($toolCall, $result) {
        echo "Tool called: {$toolCall->name}\n";
        echo "Result: " . json_encode($result) . "\n";
    });

$response = $chat->ask('What is the weather in Tokyo?');
echo "Response: " . $response->getText() . "\n\n";

// =============================================================================
// Token Usage
// =============================================================================

echo "=== Token Usage ===\n";

$chat = PHPLLM::chat();
$response = $chat->ask('Hello!');

if ($response->tokens !== null) {
    echo "Input tokens: " . $response->tokens->input . "\n";
    echo "Output tokens: " . $response->tokens->output . "\n";
    echo "Total tokens: " . $response->tokens->total() . "\n";
}

echo "\nDone!\n";
