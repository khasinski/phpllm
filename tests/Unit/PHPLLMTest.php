<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\Chat;
use PHPLLM\Exceptions\ConfigurationException;
use PHPLLM\PHPLLM;
use PHPLLM\Providers\Anthropic\AnthropicProvider;
use PHPLLM\Providers\OpenAI\OpenAIProvider;
use PHPLLM\Tests\TestCase;

class PHPLLMTest extends TestCase
{
    public function testChatReturnsChat(): void
    {
        PHPLLM::configure(['openai_api_key' => 'test-key']);

        $chat = PHPLLM::chat();

        $this->assertInstanceOf(Chat::class, $chat);
    }

    public function testChatWithModelDetectsProvider(): void
    {
        PHPLLM::configure([
            'openai_api_key' => 'test-key',
            'anthropic_api_key' => 'test-key',
        ]);

        // OpenAI model
        $chat = PHPLLM::chat('gpt-4o');
        $this->assertInstanceOf(Chat::class, $chat);

        // Anthropic model
        $chat = PHPLLM::chat('claude-3-5-sonnet-20241022');
        $this->assertInstanceOf(Chat::class, $chat);
    }

    public function testGetProviderReturnsCorrectInstance(): void
    {
        $openai = PHPLLM::getProvider('openai');
        $this->assertInstanceOf(OpenAIProvider::class, $openai);

        $anthropic = PHPLLM::getProvider('anthropic');
        $this->assertInstanceOf(AnthropicProvider::class, $anthropic);
    }

    public function testGetInvalidProviderThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown provider');

        PHPLLM::getProvider('invalid');
    }

    public function testRegisterCustomProvider(): void
    {
        PHPLLM::registerProvider('custom', OpenAIProvider::class);

        $provider = PHPLLM::getProvider('custom');
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function testRegisterCustomModel(): void
    {
        PHPLLM::registerModel('my-custom-model', 'openai');
        PHPLLM::configure(['openai_api_key' => 'test-key']);

        $chat = PHPLLM::chat('my-custom-model');
        $this->assertInstanceOf(Chat::class, $chat);
    }

    public function testListProviders(): void
    {
        $providers = PHPLLM::listProviders();

        $this->assertContains('openai', $providers);
        $this->assertContains('anthropic', $providers);
    }

    public function testConfigureReturnsConfiguration(): void
    {
        $config = PHPLLM::configure(['openai_api_key' => 'test']);

        $this->assertEquals('test', $config->getOpenaiApiKey());
    }

    public function testConfigReturnsConfiguration(): void
    {
        PHPLLM::configure(['default_model' => 'gpt-4']);

        $config = PHPLLM::config();

        $this->assertEquals('gpt-4', $config->getDefaultModel());
    }

    public function testModelPrefixDetection(): void
    {
        PHPLLM::configure([
            'openai_api_key' => 'test',
            'anthropic_api_key' => 'test',
        ]);

        // These should not throw, meaning provider detection works
        PHPLLM::chat('gpt-4-turbo-preview');
        PHPLLM::chat('claude-3-haiku-20240307');

        $this->assertTrue(true); // No exception = pass
    }

    public function testUnknownModelThrowsException(): void
    {
        PHPLLM::configure(['openai_api_key' => 'test']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Cannot detect provider for model 'unknown-model-xyz'");

        PHPLLM::chat('unknown-model-xyz');
    }

    public function testUnknownModelWithDefaultProviderWorks(): void
    {
        PHPLLM::configure([
            'openai_api_key' => 'test',
            'default_provider' => 'openai',
        ]);

        // Should use the default provider instead of throwing
        $chat = PHPLLM::chat('my-custom-model');
        $this->assertInstanceOf(Chat::class, $chat);
    }
}
