# PHPLLM Laravel Integration

This guide covers integrating PHPLLM with Laravel applications.

## Installation

```bash
composer require phpllm/phpllm
```

The service provider is auto-discovered in Laravel 5.5+.

## Configuration

### Environment Variables

Add to your `.env` file:

```env
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=...
PHPLLM_DEFAULT_MODEL=gpt-4o-mini
```

### Publish Config

```bash
php artisan vendor:publish --tag=phpllm-config
```

This creates `config/phpllm.php`:

```php
return [
    'openai_api_key' => env('OPENAI_API_KEY'),
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
    'gemini_api_key' => env('GEMINI_API_KEY'),
    'default_model' => env('PHPLLM_DEFAULT_MODEL', 'gpt-4o-mini'),
    'request_timeout' => env('PHPLLM_TIMEOUT', 120),
    'max_retries' => env('PHPLLM_MAX_RETRIES', 3),

    // Model aliases for convenience
    'model_aliases' => [
        'fast' => 'gpt-4o-mini',
        'smart' => 'gpt-5.2',
        'claude' => 'claude-sonnet-4-5-20250929',
        'local' => 'llama3.2',
    ],
];
```

## Artisan Commands

### Verify Configuration

```bash
php artisan phpllm:verify
```

Tests your API connections and shows configuration status:

```
PHPLLM Configuration Verification

OpenAI API Key: Configured
  Connection test: Passed
Anthropic API Key: Configured
  Connection test: Passed
Gemini API Key: Not configured

Default Model: gpt-4o-mini
Request Timeout: 120s
Max Retries: 3
```

### Generate Tool Classes

```bash
php artisan make:tool WeatherTool
```

Creates `app/Tools/WeatherTool.php`:

```php
<?php

namespace App\Tools;

use PHPLLM\Core\Tool;

class WeatherTool extends Tool
{
    protected string $name = 'weather_tool';
    protected string $description = 'Description of what this tool does';

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
        // Your implementation
        return ['temperature' => 22, 'condition' => 'sunny'];
    }
}
```

## Using the AI Facade

```php
use AI;

// Chat
$chat = AI::chat();
$response = $chat->ask('What is Laravel?');
echo $response->getText();

// Using model aliases
$chat = AI::chat('fast');  // Uses gpt-4o-mini
$chat = AI::chat('smart'); // Uses gpt-5.2

// Embeddings
$embedding = AI::embed('Hello, world!');
echo $embedding->dimensions; // 1536

// Image generation
$image = AI::paint('A sunset over mountains');
$image->save('sunset.png');
```

## Eloquent Integration

### Setup

Publish migrations:

```bash
php artisan vendor:publish --tag=phpllm-migrations
php artisan migrate
```

### Create Conversation Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPLLM\Integration\Laravel\Eloquent\ActsAsChat;

class Conversation extends Model
{
    use ActsAsChat;

    protected $fillable = ['model', 'title', 'user_id'];

    protected string $messagesRelation = 'messages';
    protected string $modelColumn = 'model';

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### Create Message Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'conversation_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'model',
        'tokens_input',
        'tokens_output',
        'tool_calls',
        'tool_call_id',
    ];

    protected $casts = [
        'tool_calls' => 'json',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
    ];
}
```

### Usage

```php
// Create a conversation
$conversation = Conversation::create([
    'model' => 'gpt-4o-mini',
    'user_id' => auth()->id(),
]);

// Chat with automatic persistence
$response = $conversation->ask('Hello!');
echo $response->getText();

// Messages are automatically saved
$conversation->messages; // Collection of Message models

// Continue the conversation
$response = $conversation->ask('What did I just say?');

// With system instructions
$conversation->withInstructions('You are a helpful assistant.')
    ->ask('Help me with PHP');

// Clear conversation
$conversation->clearConversation();
```

## Events

PHPLLM dispatches events you can listen to:

### Available Events

- `MessageSending` - Before a message is sent
- `MessageReceived` - After a response is received
- `ToolExecuting` - Before a tool is executed
- `ToolExecuted` - After a tool is executed

### Listening to Events

```php
// app/Providers/EventServiceProvider.php
use PHPLLM\Integration\Laravel\Events\MessageReceived;
use PHPLLM\Integration\Laravel\Events\MessageSending;

protected $listen = [
    MessageSending::class => [
        LogOutgoingMessage::class,
    ],
    MessageReceived::class => [
        LogIncomingResponse::class,
        UpdateTokenUsage::class,
    ],
];
```

### Event Listener Example

```php
<?php

namespace App\Listeners;

use PHPLLM\Integration\Laravel\Events\MessageReceived;

class UpdateTokenUsage
{
    public function handle(MessageReceived $event): void
    {
        if ($event->response->tokens && $event->conversation) {
            // Track token usage per conversation
            $event->conversation->increment(
                'total_tokens',
                $event->response->tokens->total
            );
        }
    }
}
```

## Streaming Responses

```php
use Illuminate\Support\Facades\Response;

Route::post('/chat/stream', function (Request $request) {
    return Response::stream(function () use ($request) {
        $chat = AI::chat();

        $chat->ask($request->message, stream: function ($chunk) {
            if ($chunk->hasContent()) {
                echo "data: " . json_encode([
                    'content' => $chunk->content,
                ]) . "\n\n";
                ob_flush();
                flush();
            }
        });

        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
});
```

## Tool Calling

### Define a Tool

```php
<?php

namespace App\Tools;

use App\Models\Product;
use PHPLLM\Core\Tool;

class SearchProducts extends Tool
{
    protected string $name = 'search_products';
    protected string $description = 'Search for products in the database';

    protected function parameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'Search query',
                'required' => true,
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Product category',
            ],
            'limit' => [
                'type' => 'integer',
                'default' => 5,
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $query = Product::query();

        if (!empty($arguments['query'])) {
            $query->where('name', 'like', "%{$arguments['query']}%");
        }

        if (!empty($arguments['category'])) {
            $query->where('category', $arguments['category']);
        }

        return $query
            ->take($arguments['limit'] ?? 5)
            ->get(['id', 'name', 'price', 'description'])
            ->toArray();
    }
}
```

### Use with Chat

```php
use App\Tools\SearchProducts;

$chat = AI::chat('gpt-4o')
    ->withTool(SearchProducts::class)
    ->withInstructions('You are a shopping assistant.');

$response = $chat->ask('Find me some laptops under $1000');
```

## Local Models with Ollama

PHPLLM supports local models via Ollama:

```php
// Set Ollama endpoint (default: http://localhost:11434)
PHPLLM::configure([
    'ollama_api_base' => 'http://localhost:11434',
]);

// Use local models
$chat = AI::chat('llama3.2');
$response = $chat->ask('Hello!');

// Or use the 'local' alias
$chat = AI::chat('local'); // Uses llama3.2 by default
```

## Testing

Use PHP-VCR to record and replay API calls:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use VCR\VCR;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        VCR::configure()
            ->setCassettePath(base_path('tests/fixtures/vcr'))
            ->setStorage('json');
        VCR::turnOn();
    }

    protected function tearDown(): void
    {
        VCR::eject();
        VCR::turnOff();
        parent::tearDown();
    }

    public function test_chat_responds(): void
    {
        VCR::insertCassette('chat_test.json');

        $response = $this->postJson('/api/chat', [
            'message' => 'Hello!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['response']);
    }
}
```

## Error Handling

```php
use PHPLLM\Exceptions\RateLimitException;
use PHPLLM\Exceptions\AuthenticationException;
use PHPLLM\Exceptions\ApiException;

try {
    $response = $chat->ask('Hello');
} catch (RateLimitException $e) {
    // Rate limited - wait and retry
    $retryAfter = $e->getRetryAfter() ?? 60;
    sleep($retryAfter);
    $response = $chat->ask('Hello');
} catch (AuthenticationException $e) {
    // Invalid API key
    Log::error('Invalid API key', ['provider' => $e->getMessage()]);
} catch (ApiException $e) {
    // Other API error
    Log::error('API Error', [
        'code' => $e->getStatusCode(),
        'message' => $e->getMessage(),
    ]);
}
```

## Resources

- [PHPLLM Documentation](README.md)
- [Symfony Integration](SYMFONY.md)
- [Laravel Documentation](https://laravel.com/docs)
