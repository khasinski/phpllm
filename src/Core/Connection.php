<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use PHPLLM\Exceptions\ApiException;
use PHPLLM\Exceptions\AuthenticationException;
use PHPLLM\Exceptions\RateLimitException;
use PHPLLM\Exceptions\StreamException;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP connection handler with retry logic, streaming support, and circuit breaker.
 */
final class Connection
{
    private Client $client;
    private Configuration $config;
    private ?CircuitBreaker $circuitBreaker;

    public function __construct(?Configuration $config = null, ?CircuitBreaker $circuitBreaker = null)
    {
        $this->config = $config ?? Configuration::getInstance();
        $this->client = new Client([
            'timeout' => $this->config->getRequestTimeout(),
            'connect_timeout' => 10,
        ]);
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker();
    }

    /**
     * Make a GET request.
     *
     * @param array<string, string> $headers
     * @return array<string, mixed>
     *
     * @throws ApiException If response is not valid JSON
     */
    public function get(string $url, array $headers = []): array
    {
        $startTime = microtime(true);

        Logger::logRequest('GET', $url, $headers, []);

        $response = $this->request('GET', $url, $headers);
        $content = $response->getBody()->getContents();
        $decoded = $this->decodeJson($content, $url);

        $duration = microtime(true) - $startTime;
        Logger::logResponse($response->getStatusCode(), $decoded, $duration);

        return $decoded;
    }

    /**
     * Make a POST request.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws ApiException If response is not valid JSON
     */
    public function post(string $url, array $headers, array $body): array
    {
        $startTime = microtime(true);

        Logger::logRequest('POST', $url, $headers, $body);

        $response = $this->request('POST', $url, $headers, $body);
        $content = $response->getBody()->getContents();
        $decoded = $this->decodeJson($content, $url);

        $duration = microtime(true) - $startTime;
        Logger::logResponse($response->getStatusCode(), $decoded, $duration);

        return $decoded;
    }

    /**
     * Make a streaming POST request.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @return Generator<string>
     *
     * @throws StreamException If stream fails or disconnects unexpectedly
     */
    public function stream(string $url, array $headers, array $body): Generator
    {
        $request = new Request(
            'POST',
            $url,
            array_merge($headers, ['Content-Type' => 'application/json']),
            json_encode($body),
        );

        try {
            $response = $this->client->send($request, [
                'stream' => true,
                'timeout' => $this->config->getRequestTimeout(),
            ]);
        } catch (GuzzleException $e) {
            Logger::error("Stream connection failed: {$e->getMessage()}", ['url' => $url]);
            throw new StreamException(
                "Failed to establish stream connection: {$e->getMessage()}",
                0,
                $e,
            );
        }

        $stream = $response->getBody();
        $buffer = '';

        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(1024);

                // Empty read may happen, just continue
                if ($chunk === '') {
                    continue;
                }

                $buffer .= $chunk;

                // Process complete SSE events
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    // Parse SSE data lines
                    foreach (explode("\n", $event) as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $data = substr($line, 6);
                            if ($data !== '[DONE]') {
                                yield $data;
                            }
                        }
                    }
                }
            }

            // Handle any remaining data in buffer
            if ($buffer !== '') {
                foreach (explode("\n", $buffer) as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $data = substr($line, 6);
                        if ($data !== '[DONE]') {
                            yield $data;
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            Logger::error("Stream read error: {$e->getMessage()}", [
                'url' => $url,
                'buffer_length' => strlen($buffer),
            ]);
            throw new StreamException(
                "Stream disconnected unexpectedly: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Make an HTTP request with retry logic and circuit breaker.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     */
    private function request(
        string $method,
        string $url,
        array $headers,
        ?array $body = null,
    ): ResponseInterface {
        // Extract endpoint base for circuit breaker (host + path without query)
        $endpoint = $this->getEndpointKey($url);

        // Check circuit breaker before attempting request
        $this->circuitBreaker?->allowRequest($endpoint);

        $attempts = 0;
        $maxRetries = $this->config->getMaxRetries();

        while (true) {
            try {
                $options = [
                    'headers' => array_merge($headers, ['Content-Type' => 'application/json']),
                ];

                if ($body !== null) {
                    $options['json'] = $body;
                }

                $response = $this->client->request($method, $url, $options);

                // Record success for circuit breaker
                $this->circuitBreaker?->recordSuccess($endpoint);

                return $response;
            } catch (ClientException $e) {
                // Client errors (4xx) don't affect circuit breaker - they're valid responses
                Logger::error("Client error: {$e->getMessage()}", [
                    'status' => $e->getResponse()->getStatusCode(),
                    'url' => $url,
                ]);
                $this->handleClientException($e);
            } catch (ServerException $e) {
                $attempts++;
                $this->circuitBreaker?->recordFailure($endpoint);

                Logger::warning("Server error (attempt {$attempts}/{$maxRetries}): {$e->getMessage()}", [
                    'status' => $e->getResponse()->getStatusCode(),
                    'url' => $url,
                ]);
                if ($attempts >= $maxRetries) {
                    Logger::error('Max retries exceeded for server error', ['url' => $url]);
                    throw new ApiException(
                        "Server error after {$maxRetries} retries: " . $e->getMessage(),
                        $e->getCode(),
                        null,
                        null,
                        $e,
                    );
                }
                $this->backoff($attempts);
            } catch (ConnectException $e) {
                $attempts++;
                $this->circuitBreaker?->recordFailure($endpoint);

                Logger::warning("Connection error (attempt {$attempts}/{$maxRetries}): {$e->getMessage()}", [
                    'url' => $url,
                ]);
                if ($attempts >= $maxRetries) {
                    Logger::error('Max retries exceeded for connection error', ['url' => $url]);
                    throw new ApiException(
                        "Connection failed after {$maxRetries} retries: " . $e->getMessage(),
                        0,
                        null,
                        null,
                        $e,
                    );
                }
                $this->backoff($attempts);
            }
        }
    }

    /**
     * Extract endpoint key for circuit breaker (host + base path).
     */
    private function getEndpointKey(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'unknown';
        $path = $parsed['path'] ?? '/';

        // Use first two path segments for grouping
        $pathParts = array_filter(explode('/', $path));
        $basePath = '/' . implode('/', array_slice($pathParts, 0, 2));

        return "{$host}{$basePath}";
    }

    /**
     * Perform exponential backoff with a cap.
     *
     * @param int $attempts Current attempt number
     */
    private function backoff(int $attempts): void
    {
        // Cap at 30 seconds (300000000 microseconds) to prevent overflow
        $delay = min(pow(2, $attempts) * 100000, 30000000);
        usleep((int) $delay);
    }

    /**
     * Decode JSON response with error handling.
     *
     * @return array<string, mixed>
     *
     * @throws ApiException If JSON is invalid
     */
    private function decodeJson(string $content, string $url): array
    {
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error('Invalid JSON response', [
                'url' => $url,
                'error' => json_last_error_msg(),
                'content_preview' => substr($content, 0, 200),
            ]);
            throw new ApiException(
                'Invalid JSON response from API: ' . json_last_error_msg(),
                0,
                null,
                ['raw_content' => substr($content, 0, 1000)],
            );
        }

        return $decoded ?? [];
    }

    /**
     * Handle client exceptions (4xx errors).
     */
    private function handleClientException(ClientException $e): never
    {
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);
        $message = $body['error']['message'] ?? $e->getMessage();

        match ($statusCode) {
            401, 403 => throw new AuthenticationException($message, $statusCode, null, $body, $e),
            429 => throw new RateLimitException(
                $message,
                null,
                $body,
                (int) ($response->getHeaderLine('Retry-After') ?: null),
                $e,
            ),
            default => throw new ApiException($message, $statusCode, null, $body, $e),
        };
    }
}
