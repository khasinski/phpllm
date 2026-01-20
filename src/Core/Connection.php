<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use PHPLLM\Exceptions\ApiException;
use PHPLLM\Exceptions\AuthenticationException;
use PHPLLM\Exceptions\RateLimitException;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP connection handler with retry logic and streaming support.
 */
final class Connection
{
    private Client $client;
    private Configuration $config;

    public function __construct(?Configuration $config = null)
    {
        $this->config = $config ?? Configuration::getInstance();
        $this->client = new Client([
            'timeout' => $this->config->getRequestTimeout(),
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Make a GET request.
     *
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function get(string $url, array $headers = []): array
    {
        $startTime = microtime(true);

        Logger::logRequest('GET', $url, $headers, []);

        $response = $this->request('GET', $url, $headers);
        $content = $response->getBody()->getContents();
        $decoded = json_decode($content, true) ?? [];

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
     */
    public function post(string $url, array $headers, array $body): array
    {
        $startTime = microtime(true);

        Logger::logRequest('POST', $url, $headers, $body);

        $response = $this->request('POST', $url, $headers, $body);
        $content = $response->getBody()->getContents();
        $decoded = json_decode($content, true) ?? [];

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
     */
    public function stream(string $url, array $headers, array $body): Generator
    {
        $body['stream'] = true;

        $request = new Request(
            'POST',
            $url,
            array_merge($headers, ['Content-Type' => 'application/json']),
            json_encode($body),
        );

        $response = $this->client->send($request, [
            'stream' => true,
            'timeout' => $this->config->getRequestTimeout(),
        ]);

        $stream = $response->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(1024);
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

        // Handle any remaining data
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
    }

    /**
     * Make an HTTP request with retry logic.
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

                return $this->client->request($method, $url, $options);
            } catch (ClientException $e) {
                Logger::error("Client error: {$e->getMessage()}", [
                    'status' => $e->getResponse()->getStatusCode(),
                    'url' => $url,
                ]);
                $this->handleClientException($e);
            } catch (ServerException $e) {
                $attempts++;
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
                // Exponential backoff
                usleep((int) (pow(2, $attempts) * 100000));
            } catch (ConnectException $e) {
                $attempts++;
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
                usleep((int) (pow(2, $attempts) * 100000));
            }
        }
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
