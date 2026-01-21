<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\Configuration;
use PHPLLM\Exceptions\ConfigurationException;
use PHPLLM\Tests\TestCase;
use Psr\Log\NullLogger;

class ConfigurationTest extends TestCase
{
    public function testCanConfigureApiKeys(): void
    {
        Configuration::configure([
            'openai_api_key' => 'sk-test-key',
            'anthropic_api_key' => 'ant-test-key',
        ]);

        $config = Configuration::getInstance();

        $this->assertEquals('sk-test-key', $config->getOpenaiApiKey());
        $this->assertEquals('ant-test-key', $config->getAnthropicApiKey());
    }

    public function testDefaultValues(): void
    {
        $config = Configuration::getInstance();

        $this->assertEquals('gpt-4o-mini', $config->getDefaultModel());
        $this->assertEquals('https://api.openai.com/v1', $config->getOpenaiApiBase());
        $this->assertEquals(120, $config->getRequestTimeout());
        $this->assertEquals(3, $config->getMaxRetries());
    }

    public function testCanSetDefaultModel(): void
    {
        Configuration::configure([
            'default_model' => 'gpt-4o',
        ]);

        $this->assertEquals('gpt-4o', Configuration::getInstance()->getDefaultModel());
    }

    public function testFluentInterface(): void
    {
        $config = Configuration::getInstance()
            ->setOpenaiApiKey('key1')
            ->setAnthropicApiKey('key2')
            ->setRequestTimeout(60);

        $this->assertEquals('key1', $config->getOpenaiApiKey());
        $this->assertEquals('key2', $config->getAnthropicApiKey());
        $this->assertEquals(60, $config->getRequestTimeout());
    }

    public function testToArrayMasksSecrets(): void
    {
        Configuration::configure([
            'openai_api_key' => 'sk-secret-key',
        ]);

        $array = Configuration::getInstance()->toArray();

        $this->assertEquals('***', $array['openai_api_key']);
    }

    public function testResetClearsConfiguration(): void
    {
        Configuration::configure([
            'openai_api_key' => 'test-key',
        ]);

        Configuration::reset();

        $this->assertNull(Configuration::getInstance()->getOpenaiApiKey());
    }

    public function testUnknownConfigKeyThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown configuration keys: invalid_key');

        Configuration::configure([
            'invalid_key' => 'value',
        ]);
    }

    public function testMultipleUnknownKeysListedInException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown configuration keys: foo, bar');

        Configuration::configure([
            'foo' => 'value1',
            'bar' => 'value2',
        ]);
    }

    public function testDirectInstantiationForDI(): void
    {
        $config = new Configuration([
            'openai_api_key' => 'di-key',
            'default_model' => 'gpt-4o',
        ]);

        $this->assertEquals('di-key', $config->getOpenaiApiKey());
        $this->assertEquals('gpt-4o', $config->getDefaultModel());
    }

    public function testDirectInstantiationValidatesKeys(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown configuration keys');

        new Configuration(['bad_key' => 'value']);
    }

    public function testLoggerAutoWiring(): void
    {
        $logger = new NullLogger();

        Configuration::configure([
            'logging_enabled' => true,
            'logger' => $logger,
        ]);

        $config = Configuration::getInstance();
        $this->assertTrue($config->isLoggingEnabled());
        $this->assertSame($logger, $config->getLogger());
    }

    public function testModelAliasResolution(): void
    {
        $config = Configuration::getInstance();

        // Built-in aliases
        $this->assertEquals('gpt-4o-mini', $config->resolveModelAlias('fast'));
        $this->assertEquals('claude-sonnet-4-5-20250929', $config->resolveModelAlias('claude'));

        // Unknown alias returns original
        $this->assertEquals('custom-model', $config->resolveModelAlias('custom-model'));
    }

    public function testCustomModelAlias(): void
    {
        Configuration::configure([
            'model_aliases' => ['mymodel' => 'gpt-4o'],
        ]);

        $config = Configuration::getInstance();
        $this->assertEquals('gpt-4o', $config->resolveModelAlias('mymodel'));
    }
}
