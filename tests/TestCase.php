<?php

declare(strict_types=1);

namespace PHPLLM\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PHPLLM\PHPLLM;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PHPLLM::reset();
    }

    protected function tearDown(): void
    {
        PHPLLM::reset();
        parent::tearDown();
    }

    /**
     * Configure PHPLLM with test API keys.
     */
    protected function configureWithEnv(): void
    {
        PHPLLM::configure([
            'openai_api_key' => getenv('OPENAI_API_KEY') ?: null,
            'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: null,
        ]);
    }

    /**
     * Skip test if API key is not available.
     */
    protected function requireOpenAI(): void
    {
        if (!getenv('OPENAI_API_KEY')) {
            $this->markTestSkipped('OPENAI_API_KEY not set');
        }
        $this->configureWithEnv();
    }

    /**
     * Skip test if Anthropic key is not available.
     */
    protected function requireAnthropic(): void
    {
        if (!getenv('ANTHROPIC_API_KEY')) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }
        $this->configureWithEnv();
    }
}
