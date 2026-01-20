# PHPLLM Symfony Integration

This guide covers integrating PHPLLM with Symfony applications.

## Installation

```bash
composer require phpllm/phpllm
```

## Configuration

### Option 1: Bundle (Symfony 6.1+)

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    PHPLLM\Integration\Symfony\PHPLLMBundle::class => ['all' => true],
];
```

Create `config/packages/phpllm.yaml`:

```yaml
phpllm:
    openai_api_key: '%env(OPENAI_API_KEY)%'
    anthropic_api_key: '%env(ANTHROPIC_API_KEY)%'
    default_model: 'gpt-4o-mini'
    request_timeout: 120
    max_retries: 3
```

### Option 2: Manual Configuration (Any Symfony Version)

Create a service configuration in `config/services.yaml`:

```yaml
services:
    PHPLLM\Core\Configuration:
        factory: ['PHPLLM\Core\Configuration', 'getInstance']
        calls:
            - setOpenaiApiKey: ['%env(OPENAI_API_KEY)%']
            - setAnthropicApiKey: ['%env(ANTHROPIC_API_KEY)%']
            - setDefaultModel: ['gpt-4o-mini']

    phpllm:
        class: PHPLLM\PHPLLM
        public: true
```

## Environment Variables

Add to your `.env` file:

```env
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

## Usage in Controllers

### Basic Usage

```php
<?php

namespace App\Controller;

use PHPLLM\PHPLLM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    #[Route('/chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $message = $request->request->get('message');

        $chat = PHPLLM::chat();
        $response = $chat->ask($message);

        return $this->json([
            'response' => $response->getText(),
            'tokens' => $response->tokens?->toArray(),
        ]);
    }
}
```

### With Dependency Injection

```php
<?php

namespace App\Controller;

use PHPLLM\Core\Configuration;
use PHPLLM\PHPLLM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AiController extends AbstractController
{
    public function __construct(
        private Configuration $config,
    ) {
    }

    #[Route('/ai/status')]
    public function status(): Response
    {
        return $this->json([
            'configured' => $this->config->getOpenaiApiKey() !== null,
            'default_model' => $this->config->getDefaultModel(),
            'providers' => PHPLLM::listProviders(),
        ]);
    }
}
```

## Usage in Services

```php
<?php

namespace App\Service;

use PHPLLM\Core\Chat;
use PHPLLM\Core\Tool;
use PHPLLM\PHPLLM;

class AiAssistant
{
    private Chat $chat;

    public function __construct(
        private string $model = 'gpt-4o',
        private string $instructions = 'You are a helpful assistant.',
    ) {
        $this->chat = PHPLLM::chat($this->model)
            ->withInstructions($this->instructions);
    }

    public function ask(string $message): string
    {
        return $this->chat->ask($message)->getText();
    }

    public function withTool(Tool $tool): self
    {
        $this->chat->withTool($tool);
        return $this;
    }
}
```

Register as a service:

```yaml
# config/services.yaml
services:
    App\Service\AiAssistant:
        arguments:
            $model: 'gpt-4o'
            $instructions: 'You are a helpful customer support agent.'
```

## Streaming with Symfony

### Using Streamed Response

```php
<?php

namespace App\Controller;

use PHPLLM\PHPLLM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class StreamController extends AbstractController
{
    #[Route('/stream', methods: ['POST'])]
    public function stream(Request $request): StreamedResponse
    {
        $message = $request->request->get('message');

        return new StreamedResponse(function () use ($message) {
            $chat = PHPLLM::chat();

            $chat->ask($message, stream: function ($chunk) {
                if ($chunk->hasContent()) {
                    echo "data: " . json_encode(['content' => $chunk->content]) . "\n\n";
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
    }
}
```

## Embeddings Example

```php
<?php

namespace App\Service;

use PHPLLM\Core\Embedding;
use PHPLLM\PHPLLM;

class SemanticSearch
{
    /** @var array<string, Embedding> */
    private array $index = [];

    public function index(string $id, string $text): void
    {
        $this->index[$id] = PHPLLM::embed($text);
    }

    /**
     * @return array<array{id: string, score: float}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $queryEmbedding = PHPLLM::embed($query);

        $results = [];
        foreach ($this->index as $id => $embedding) {
            $results[] = [
                'id' => $id,
                'score' => $queryEmbedding->similarity($embedding),
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }
}
```

## Image Generation Example

```php
<?php

namespace App\Controller;

use PHPLLM\PHPLLM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends AbstractController
{
    #[Route('/generate-image', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        $prompt = $request->request->get('prompt');

        $image = PHPLLM::paint($prompt, options: [
            'size' => '1024x1024',
            'quality' => 'hd',
        ]);

        // Save to filesystem
        $filename = uniqid('generated_') . '.png';
        $path = $this->getParameter('kernel.project_dir') . '/public/images/' . $filename;
        $image->save($path);

        return $this->json([
            'url' => '/images/' . $filename,
            'revised_prompt' => $image->revisedPrompt,
        ]);
    }
}
```

## Tool Calling with Symfony

```php
<?php

namespace App\Tool;

use App\Repository\ProductRepository;
use PHPLLM\Core\Tool;

class SearchProducts extends Tool
{
    protected string $name = 'search_products';
    protected string $description = 'Search for products in the catalog';

    public function __construct(
        private ProductRepository $productRepository,
    ) {
    }

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
            'max_results' => [
                'type' => 'integer',
                'default' => 5,
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $products = $this->productRepository->search(
            query: $arguments['query'],
            category: $arguments['category'] ?? null,
            limit: $arguments['max_results'] ?? 5,
        );

        return array_map(fn($p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'price' => $p->getPrice(),
        ], $products);
    }
}
```

Register and use:

```yaml
# config/services.yaml
services:
    App\Tool\SearchProducts:
        arguments:
            $productRepository: '@App\Repository\ProductRepository'
```

```php
// In controller
$searchTool = $this->container->get(SearchProducts::class);

$chat = PHPLLM::chat('gpt-4o')
    ->withTool($searchTool)
    ->withInstructions('You are a shopping assistant.');

$response = $chat->ask('Find me some red shoes under $100');
```

## Testing

Use PHP-VCR to record and replay API calls:

```php
<?php

namespace App\Tests\Service;

use App\Service\AiAssistant;
use PHPUnit\Framework\TestCase;
use VCR\VCR;

class AiAssistantTest extends TestCase
{
    protected function setUp(): void
    {
        VCR::configure()
            ->setCassettePath(__DIR__ . '/fixtures')
            ->setStorage('json');
        VCR::turnOn();
    }

    protected function tearDown(): void
    {
        VCR::eject();
        VCR::turnOff();
    }

    public function testAsk(): void
    {
        VCR::insertCassette('ai_assistant_ask.json');

        $assistant = new AiAssistant();
        $response = $assistant->ask('Hello');

        $this->assertNotEmpty($response);
    }
}
```

## Logging

Enable logging with Monolog:

```php
<?php

namespace App\EventSubscriber;

use PHPLLM\Core\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PHPLLMLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        Logger::setLogger($this->logger);
        Logger::setEnabled(true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
```

## Resources

- [PHPLLM Documentation](README.md)
- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference)
- [Anthropic API Reference](https://docs.anthropic.com/en/api)
