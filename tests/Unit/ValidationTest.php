<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use InvalidArgumentException;
use PHPLLM\Core\Chat;
use PHPLLM\Core\Configuration;
use PHPLLM\Providers\OpenAI\OpenAIProvider;
use PHPLLM\Tests\TestCase;

class ValidationTest extends TestCase
{
    public function testRequestTimeoutValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request timeout must be at least 1 second');

        Configuration::getInstance()->setRequestTimeout(0);
    }

    public function testRequestTimeoutMaxValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request timeout cannot exceed 600 seconds');

        Configuration::getInstance()->setRequestTimeout(601);
    }

    public function testMaxRetriesNegativeValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max retries cannot be negative');

        Configuration::getInstance()->setMaxRetries(-1);
    }

    public function testMaxRetriesMaxValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max retries cannot exceed 10');

        Configuration::getInstance()->setMaxRetries(11);
    }

    public function testDefaultTemperatureValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 2.0');

        Configuration::getInstance()->setDefaultTemperature(2.5);
    }

    public function testDefaultTemperatureNegativeValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 2.0');

        Configuration::getInstance()->setDefaultTemperature(-0.1);
    }

    public function testDefaultMaxTokensValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be at least 1');

        Configuration::getInstance()->setDefaultMaxTokens(0);
    }

    public function testChatTemperatureValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 2.0');

        Configuration::configure(['openai_api_key' => 'test']);
        $provider = new OpenAIProvider();
        $chat = new Chat($provider, 'gpt-4o-mini');
        $chat->withTemperature(3.0);
    }

    public function testChatMaxTokensValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be at least 1');

        Configuration::configure(['openai_api_key' => 'test']);
        $provider = new OpenAIProvider();
        $chat = new Chat($provider, 'gpt-4o-mini');
        $chat->withMaxTokens(0);
    }

    public function testValidValuesAccepted(): void
    {
        $config = Configuration::getInstance();

        // Valid timeout
        $config->setRequestTimeout(60);
        $this->assertEquals(60, $config->getRequestTimeout());

        // Valid retries
        $config->setMaxRetries(5);
        $this->assertEquals(5, $config->getMaxRetries());

        // Valid temperature
        $config->setDefaultTemperature(0.7);
        $this->assertEquals(0.7, $config->getDefaultTemperature());

        // Edge cases
        $config->setDefaultTemperature(0.0);
        $this->assertEquals(0.0, $config->getDefaultTemperature());

        $config->setDefaultTemperature(2.0);
        $this->assertEquals(2.0, $config->getDefaultTemperature());

        // Null is allowed
        $config->setDefaultTemperature(null);
        $this->assertNull($config->getDefaultTemperature());
    }
}
