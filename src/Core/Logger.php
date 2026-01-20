<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Simple logger wrapper that supports PSR-3 loggers.
 */
final class Logger
{
    private static ?LoggerInterface $logger = null;
    private static bool $enabled = false;

    /**
     * Set the PSR-3 logger instance.
     */
    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
        self::$enabled = $logger !== null;
    }

    /**
     * Enable or disable logging.
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if logging is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled && self::$logger !== null;
    }

    /**
     * Log a debug message.
     *
     * @param array<string, mixed> $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a message at the given level.
     *
     * @param array<string, mixed> $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        // Filter sensitive data
        $context = self::filterSensitiveData($context);

        self::$logger->log($level, "[PHPLLM] {$message}", $context);
    }

    /**
     * Filter sensitive data from context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function filterSensitiveData(array $context): array
    {
        $sensitiveKeys = [
            'api_key', 'apikey', 'api-key',
            'authorization', 'auth',
            'password', 'secret', 'token',
            'x-api-key', 'anthropic-api-key',
        ];

        array_walk_recursive($context, function (&$value, $key) use ($sensitiveKeys) {
            if (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains($lowerKey, $sensitive)) {
                        $value = '***REDACTED***';
                        return;
                    }
                }
            }

            // Also filter bearer tokens in strings
            if (is_string($value) && preg_match('/Bearer\s+\S+/i', $value)) {
                $value = preg_replace('/Bearer\s+\S+/i', 'Bearer ***REDACTED***', $value);
            }
        });

        return $context;
    }

    /**
     * Log an API request.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     */
    public static function logRequest(string $method, string $url, array $headers, ?array $body = null): void
    {
        self::debug("API Request: {$method} {$url}", [
            'headers' => $headers,
            'body' => $body ? self::truncateBody($body) : null,
        ]);
    }

    /**
     * Log an API response.
     *
     * @param array<string, mixed> $body
     */
    public static function logResponse(int $statusCode, array $body, float $duration): void
    {
        self::debug("API Response: {$statusCode}", [
            'status' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'body' => self::truncateBody($body),
        ]);
    }

    /**
     * Truncate large body content for logging.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function truncateBody(array $body): array
    {
        $maxLength = 1000;

        array_walk_recursive($body, function (&$value) use ($maxLength) {
            if (is_string($value) && strlen($value) > $maxLength) {
                $value = substr($value, 0, $maxLength) . '... [truncated]';
            }
        });

        return $body;
    }
}
