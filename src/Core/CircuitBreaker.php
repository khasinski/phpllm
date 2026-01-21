<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use PHPLLM\Exceptions\ApiException;

/**
 * Circuit breaker to prevent cascading failures.
 *
 * When too many consecutive failures occur, the circuit "opens" and
 * subsequent requests fail fast without hitting the API. After a
 * cooldown period, the circuit enters "half-open" state and allows
 * a single request through. If it succeeds, the circuit closes.
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Too many failures, requests fail immediately
 * - HALF_OPEN: Testing if service recovered, one request allowed
 *
 * Each CircuitBreaker instance maintains its own state, allowing for
 * isolated circuit breakers per provider or connection.
 */
final class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /** @var array<string, array{state: string, failures: int, last_failure: float, successes: int}> */
    private array $circuits = [];

    private int $failureThreshold;
    private int $cooldownSeconds;
    private int $successThreshold;

    public function __construct(
        int $failureThreshold = 5,
        int $cooldownSeconds = 30,
        int $successThreshold = 2,
    ) {
        $this->failureThreshold = $failureThreshold;
        $this->cooldownSeconds = $cooldownSeconds;
        $this->successThreshold = $successThreshold;
    }

    /**
     * Check if a request should be allowed for this endpoint.
     *
     * @throws ApiException If circuit is open
     */
    public function allowRequest(string $endpoint): void
    {
        $circuit = $this->getCircuit($endpoint);

        switch ($circuit['state']) {
            case self::STATE_OPEN:
                // Check if cooldown period has passed
                if (microtime(true) - $circuit['last_failure'] >= $this->cooldownSeconds) {
                    // Move to half-open state
                    $this->setState($endpoint, self::STATE_HALF_OPEN);
                    Logger::info("Circuit breaker half-open for: {$endpoint}");
                    return;
                }

                Logger::warning("Circuit breaker open, rejecting request to: {$endpoint}", [
                    'failures' => $circuit['failures'],
                    'cooldown_remaining' => $this->cooldownSeconds - (microtime(true) - $circuit['last_failure']),
                ]);

                throw new ApiException(
                    "Service temporarily unavailable (circuit breaker open for {$endpoint})",
                    503,
                );

            case self::STATE_HALF_OPEN:
                // Allow one request through to test
                return;

            case self::STATE_CLOSED:
            default:
                return;
        }
    }

    /**
     * Record a successful request.
     */
    public function recordSuccess(string $endpoint): void
    {
        $circuit = $this->getCircuit($endpoint);

        if ($circuit['state'] === self::STATE_HALF_OPEN) {
            $circuit['successes']++;

            if ($circuit['successes'] >= $this->successThreshold) {
                // Service recovered, close the circuit
                $this->setState($endpoint, self::STATE_CLOSED);
                $this->circuits[$endpoint]['failures'] = 0;
                $this->circuits[$endpoint]['successes'] = 0;
                Logger::info("Circuit breaker closed for: {$endpoint}");
            } else {
                $this->circuits[$endpoint] = $circuit;
            }
        } elseif ($circuit['state'] === self::STATE_CLOSED && $circuit['failures'] > 0) {
            // Reset failure count on success
            $this->circuits[$endpoint]['failures'] = 0;
        }
    }

    /**
     * Record a failed request.
     */
    public function recordFailure(string $endpoint): void
    {
        $circuit = $this->getCircuit($endpoint);

        if ($circuit['state'] === self::STATE_HALF_OPEN) {
            // Failed during recovery attempt, reopen the circuit
            $this->setState($endpoint, self::STATE_OPEN);
            $this->circuits[$endpoint]['last_failure'] = microtime(true);
            $this->circuits[$endpoint]['successes'] = 0;
            Logger::warning("Circuit breaker reopened for: {$endpoint}");
            return;
        }

        $circuit['failures']++;
        $circuit['last_failure'] = microtime(true);
        $this->circuits[$endpoint] = $circuit;

        if ($circuit['failures'] >= $this->failureThreshold && $circuit['state'] === self::STATE_CLOSED) {
            $this->setState($endpoint, self::STATE_OPEN);
            Logger::warning("Circuit breaker opened for: {$endpoint}", [
                'failures' => $circuit['failures'],
                'threshold' => $this->failureThreshold,
            ]);
        }
    }

    /**
     * Get the current state of a circuit.
     *
     * @return array{state: string, failures: int, last_failure: float, successes: int}
     */
    public function getCircuit(string $endpoint): array
    {
        if (!isset($this->circuits[$endpoint])) {
            $this->circuits[$endpoint] = [
                'state' => self::STATE_CLOSED,
                'failures' => 0,
                'last_failure' => 0.0,
                'successes' => 0,
            ];
        }

        return $this->circuits[$endpoint];
    }

    /**
     * Check if circuit is open for an endpoint.
     */
    public function isOpen(string $endpoint): bool
    {
        return $this->getCircuit($endpoint)['state'] === self::STATE_OPEN;
    }

    /**
     * Reset circuit for an endpoint.
     */
    public function reset(string $endpoint): void
    {
        unset($this->circuits[$endpoint]);
    }

    /**
     * Reset all circuits for this instance.
     */
    public function resetAll(): void
    {
        $this->circuits = [];
    }

    private function setState(string $endpoint, string $state): void
    {
        if (!isset($this->circuits[$endpoint])) {
            $this->getCircuit($endpoint);
        }
        $this->circuits[$endpoint]['state'] = $state;
    }
}
