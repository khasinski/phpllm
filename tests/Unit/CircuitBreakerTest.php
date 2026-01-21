<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\CircuitBreaker;
use PHPLLM\Exceptions\ApiException;
use PHPLLM\Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets a fresh CircuitBreaker instance (no shared state)
        $this->breaker = new CircuitBreaker(
            failureThreshold: 3,
            cooldownSeconds: 1,
            successThreshold: 2,
        );
    }

    public function testAllowsRequestsWhenClosed(): void
    {
        // Should not throw
        $this->breaker->allowRequest('api.example.com/v1');
        $this->assertFalse($this->breaker->isOpen('api.example.com/v1'));
    }

    public function testOpensAfterThresholdFailures(): void
    {
        $endpoint = 'api.example.com/v1';

        // Record failures up to threshold
        $this->breaker->recordFailure($endpoint);
        $this->breaker->recordFailure($endpoint);
        $this->assertFalse($this->breaker->isOpen($endpoint));

        // One more should open the circuit
        $this->breaker->recordFailure($endpoint);
        $this->assertTrue($this->breaker->isOpen($endpoint));
    }

    public function testRejectsRequestsWhenOpen(): void
    {
        $endpoint = 'api.example.com/v1';

        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure($endpoint);
        }

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('circuit breaker open');
        $this->breaker->allowRequest($endpoint);
    }

    public function testSuccessResetsFailureCount(): void
    {
        $endpoint = 'api.example.com/v1';

        // Record some failures
        $this->breaker->recordFailure($endpoint);
        $this->breaker->recordFailure($endpoint);

        // Success should reset
        $this->breaker->recordSuccess($endpoint);

        // Should be able to have more failures before opening
        $this->breaker->recordFailure($endpoint);
        $this->breaker->recordFailure($endpoint);
        $this->assertFalse($this->breaker->isOpen($endpoint));
    }

    public function testHalfOpenAfterCooldown(): void
    {
        $endpoint = 'api.example.com/v1';

        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure($endpoint);
        }

        // Wait for cooldown
        usleep(1100000); // 1.1 seconds

        // Should allow request (half-open state)
        $this->breaker->allowRequest($endpoint);
        $circuit = $this->breaker->getCircuit($endpoint);
        $this->assertEquals('half_open', $circuit['state']);
    }

    public function testClosesAfterSuccessThreshold(): void
    {
        $endpoint = 'api.example.com/v1';

        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure($endpoint);
        }

        // Wait for cooldown
        usleep(1100000);

        // Enter half-open
        $this->breaker->allowRequest($endpoint);

        // Record successes
        $this->breaker->recordSuccess($endpoint);
        $this->breaker->recordSuccess($endpoint);

        // Should be closed now
        $circuit = $this->breaker->getCircuit($endpoint);
        $this->assertEquals('closed', $circuit['state']);
    }

    public function testReopensOnFailureDuringHalfOpen(): void
    {
        $endpoint = 'api.example.com/v1';

        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure($endpoint);
        }

        // Wait for cooldown
        usleep(1100000);

        // Enter half-open
        $this->breaker->allowRequest($endpoint);

        // Fail during half-open
        $this->breaker->recordFailure($endpoint);

        // Should be open again
        $this->assertTrue($this->breaker->isOpen($endpoint));
    }

    public function testDifferentEndpointsAreIndependent(): void
    {
        $endpoint1 = 'api.example.com/v1';
        $endpoint2 = 'api.other.com/v1';

        // Open circuit for endpoint1
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure($endpoint1);
        }

        $this->assertTrue($this->breaker->isOpen($endpoint1));
        $this->assertFalse($this->breaker->isOpen($endpoint2));

        // Should allow requests to endpoint2
        $this->breaker->allowRequest($endpoint2);
    }
}
