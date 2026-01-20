<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\Configuration;
use PHPLLM\Tests\TestCase;

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
}
