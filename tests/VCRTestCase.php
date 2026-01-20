<?php

declare(strict_types=1);

namespace PHPLLM\Tests;

use VCR\VCR;

/**
 * Test case with VCR support for recording/replaying HTTP requests.
 *
 * To record new cassettes:
 * 1. Set API keys in environment
 * 2. Delete the cassette file
 * 3. Run the test
 *
 * The cassette will be recorded and subsequent runs will replay it.
 */
abstract class VCRTestCase extends TestCase
{
    protected static bool $vcrEnabled = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (static::$vcrEnabled) {
            VCR::configure()
                ->setCassettePath(__DIR__ . '/fixtures/vcr')
                ->setStorage('json')
                ->enableRequestMatchers(['method', 'url', 'body']);

            VCR::turnOn();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$vcrEnabled) {
            VCR::turnOff();
        }

        parent::tearDownAfterClass();
    }

    /**
     * Use a specific cassette for this test.
     */
    protected function useCassette(string $name): void
    {
        VCR::insertCassette($name . '.json');
    }

    protected function tearDown(): void
    {
        if (static::$vcrEnabled) {
            VCR::eject();
        }

        parent::tearDown();
    }
}
