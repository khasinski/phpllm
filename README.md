# PHPLLM

One beautiful PHP API for OpenAI, Anthropic, Gemini, and more.

[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Inspired by [RubyLLM](https://rubyllm.com/), PHPLLM provides a unified, elegant interface for working with Large Language Models in PHP.

## Features

- 🔄 **Unified API** - Same interface for OpenAI, Anthropic, and more
- 💬 **Chat** - Multi-turn conversations with any model
- 👁️ **Vision** - Analyze images and documents
- 🔧 **Tools** - Function calling with automatic execution
- 📡 **Streaming** - Real-time response streaming
- 🧮 **Embeddings** - Generate and compare text embeddings
- 🎨 **Image Generation** - Create images with GPT Image 1.5
- ⚡ **Framework Ready** - Laravel & Symfony integration

## Installation

```bash
composer require phpllm/phpllm
```

## Quick Start

```php
use PHPLLM\PHPLLM;

// Configure with your API keys
PHPLLM::configure([
    'openai_api_key' => getenv('OPENAI_API_KEY'),
    'anthropic_api_key' => getenv('ANTHROPIC_API_KEY'),
]);

// Start chatting
$chat = PHPLLM::chat();
$response = $chat->ask('What is PHP?');
echo $response->getText();
```

## Usage

### Chat

```php
// Default model (gpt-4o-mini)
$chat = PHPLLM::chat();

// Specific model
$chat = PHPLLM::chat('gpt-5.2');           // OpenAI GPT-5.2 (latest)
$chat = PHPLLM::chat('gpt-5.2-codex');     // Optimized for coding
$chat = PHPLLM::chat('claude-sonnet-4-5-20250929'); // Latest Claude

// With configuration
$chat = PHPLLM::chat('gpt-4o')
    ->withInstructions('You are a helpful PHP expert.')
    ->withTemperature(0.7)
    ->withMaxTokens(1000);

$response = $chat->ask('Explain traits in PHP');
echo $response->getText();
```

### Multi-turn Conversations

```php
$chat = PHPLLM::chat();

$chat->ask('My name is Alice.');
$response = $chat->ask('What is my name?');
// "Your name is Alice."
```

### Vision (Images & PDFs)

```php
$chat = PHPLLM::chat('gpt-4o');

// Use explicit named arguments for clarity
$chat->ask('What is in this image?', image: 'photo.jpg');
$chat->ask('Describe this', image: 'https://example.com/image.png');
$chat->ask('Compare these', image: ['image1.jpg', 'image2.jpg']);

// PDF documents
$chat = PHPLLM::chat('claude-sonnet-4-5-20250929');
$chat->ask('Summarize this document', pdf: 'report.pdf');

// Audio files
$chat->ask('Transcribe this', audio: 'recording.mp3');

// Generic file (auto-detected type)
$chat->ask('Process this', file: 'document.pdf');

// Using Attachment objects for more control
use PHPLLM\Core\Attachment;

$chat->ask('Analyze', image: Attachment::image('/path/to/photo.jpg'));
$chat->ask('From URL', image: Attachment::imageUrl('https://example.com/img.png'));
$chat->ask('Read PDF', pdf: Attachment::pdf('/path/to/doc.pdf'));
```

### Streaming

```php
$chat = PHPLLM::chat();

$response = $chat->ask('Tell me a story', stream: function ($chunk) {
    echo $chunk->content;
    flush();
});
```

### Tool Calling

```php
use PHPLLM\Core\Tool;

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
        // Call your weather API
        return ['temperature' => 22, 'condition' => 'sunny'];
    }
}

$chat = PHPLLM::chat('gpt-4o')
    ->withTool(GetWeather::class)
    ->onToolCall(function ($call, $result) {
        echo "Called {$call->name}\n";
    });

$response = $chat->ask('What is the weather in Tokyo?');
```

### Embeddings

```php
// Single text
$embedding = PHPLLM::embed('Hello, world!');
echo $embedding->dimensions; // 1536

// Multiple texts
$embeddings = PHPLLM::embed(['Hello', 'World', 'Test']);

// Compare similarity
$sim = $embeddings[0]->similarity($embeddings[1]);
echo "Similarity: {$sim}"; // 0.0 to 1.0

// Custom model
$embedding = PHPLLM::embed('Hello', model: 'text-embedding-3-large');
```

### Image Generation

```php
// Generate an image (uses gpt-image-1.5 by default)
$image = PHPLLM::paint('A sunset over mountains');
echo $image->url;

// Save to file
$image->save('sunset.png');

// Options
$image = PHPLLM::paint('A cat', options: [
    'size' => '1024x1024',
    'quality' => 'hd',
    'style' => 'vivid',
]);

// Multiple images
$images = PHPLLM::paintMany('A dog', count: 3);

// Use legacy DALL-E 3 if needed (deprecated)
$image = PHPLLM::paint('A cat', model: 'dall-e-3');
```

## Framework Integration

### Laravel

See detailed Laravel examples below. For **Symfony**, see [SYMFONY.md](SYMFONY.md).

### Laravel Installation

```bash
composer require phpllm/phpllm
```

Add the service provider (auto-discovered in Laravel 5.5+):

```php
// config/app.php
'providers' => [
    PHPLLM\Integration\Laravel\PHPLLMServiceProvider::class,
],

'aliases' => [
    'AI' => PHPLLM\Integration\Laravel\Facades\AI::class,
],
```

Publish the config:

```bash
php artisan vendor:publish --tag=phpllm-config
```

### Configuration

```env
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
PHPLLM_DEFAULT_MODEL=gpt-4o-mini
```

### Using the Facade

```php
use AI;

$chat = AI::chat();
$response = $chat->ask('Hello!');

$embedding = AI::embed('Hello');
$image = AI::paint('A sunset');
```

### Eloquent Integration

```php
use PHPLLM\Integration\Laravel\Eloquent\ActsAsChat;

class Conversation extends Model
{
    use ActsAsChat;

    protected string $messagesRelation = 'messages';
    protected string $modelColumn = 'ai_model';

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}

// Usage
$conversation = Conversation::create(['ai_model' => 'gpt-4o']);
$response = $conversation->ask('Hello!');
// Messages are automatically persisted
```

## Supported Providers

| Provider | Chat | Vision | Tools | Streaming | Embeddings | Images |
|----------|------|--------|-------|-----------|------------|--------|
| OpenAI   | ✅   | ✅     | ✅    | ✅        | ✅         | ✅     |
| Anthropic| ✅   | ✅     | ✅    | ✅        | ❌         | ❌     |
| Gemini   | 🚧   | 🚧     | 🚧    | 🚧        | 🚧         | 🚧     |

## Configuration Options

```php
PHPLLM::configure([
    // API Keys
    'openai_api_key' => 'sk-...',
    'anthropic_api_key' => 'sk-ant-...',
    'gemini_api_key' => '...',

    // Custom endpoints
    'openai_api_base' => 'https://api.openai.com/v1',
    'ollama_api_base' => 'http://localhost:11434',

    // Defaults
    'default_model' => 'gpt-4o-mini',
    'default_provider' => 'openai',  // Used for unknown models

    // Request settings
    'request_timeout' => 120,
    'max_retries' => 3,

    // Logging (PSR-3 compatible)
    'logging_enabled' => true,
    'logger' => $psrLogger,  // Auto-wired when both are set

    // Model aliases
    'model_aliases' => [
        'fast' => 'gpt-4o-mini',
        'smart' => 'claude-opus-4-5-20251101',
    ],
]);
```

### Dependency Injection

For DI containers, instantiate classes directly instead of using the facade:

```php
use PHPLLM\Core\Configuration;
use PHPLLM\Core\Chat;
use PHPLLM\Providers\OpenAI\OpenAIProvider;

// Create configuration for DI
$config = new Configuration([
    'openai_api_key' => $apiKey,
    'default_model' => 'gpt-4o',
]);

// Inject provider and create chat
$provider = new OpenAIProvider($config);
$chat = new Chat($provider, 'gpt-4o');

$response = $chat->ask('Hello!');
```

## Error Handling

```php
use PHPLLM\Exceptions\RateLimitException;
use PHPLLM\Exceptions\AuthenticationException;
use PHPLLM\Exceptions\ApiException;
use PHPLLM\Exceptions\ConfigurationException;
use PHPLLM\Exceptions\ToolExecutionException;

try {
    $response = $chat->ask('Hello');
} catch (RateLimitException $e) {
    // Wait and retry
    sleep($e->getRetryAfter() ?? 60);
} catch (AuthenticationException $e) {
    // Invalid API key
} catch (ApiException $e) {
    // Other API error
    echo $e->getStatusCode();
    echo $e->getMessage();
}

// Configuration errors (invalid keys, unknown models)
try {
    PHPLLM::configure(['invalid_key' => 'value']);
} catch (ConfigurationException $e) {
    // Unknown configuration keys: invalid_key
}

try {
    PHPLLM::chat('unknown-model-xyz');
} catch (ConfigurationException $e) {
    // Cannot detect provider for model 'unknown-model-xyz'
}

// Tool execution errors
try {
    $chat->withTool(MyTool::class)->ask('Use the tool');
} catch (ToolExecutionException $e) {
    echo "Tool '{$e->toolName}' failed: {$e->getMessage()}";
    echo "Arguments: " . json_encode($e->arguments);
}
```

## Testing

PHPLLM uses [PHP-VCR](https://php-vcr.github.io/) for integration tests:

```bash
# Run all tests
composer test

# Run unit tests only
vendor/bin/phpunit tests/Unit

# Run with coverage
vendor/bin/phpunit --coverage-html coverage
```

To record new cassettes:
1. Set your API keys in `.env`
2. Delete the cassette file in `tests/fixtures/vcr/`
3. Run the test

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

- Inspired by [RubyLLM](https://rubyllm.com/) by Carmine Paolino
- Built with ❤️ for the PHP community
