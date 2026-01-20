#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scrub sensitive data (API keys) from VCR cassette files.
 *
 * Usage: php scripts/scrub-cassettes.php
 */

$cassettePath = __DIR__ . '/../tests/fixtures/vcr';

if (!is_dir($cassettePath)) {
    echo "No cassettes directory found at: $cassettePath\n";
    exit(0);
}

$sensitiveHeaders = [
    'Authorization',
    'x-api-key',
    'api-key',
];

$files = glob($cassettePath . '/*.json');
$scrubbed = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $data = json_decode($content, true);

    if ($data === null) {
        echo "Warning: Could not parse $file\n";
        continue;
    }

    $modified = false;

    foreach ($data as &$recording) {
        if (!isset($recording['request']['headers'])) {
            continue;
        }

        foreach ($sensitiveHeaders as $header) {
            // Check various case combinations
            $headerVariants = [
                $header,
                strtolower($header),
                strtoupper($header),
            ];

            foreach ($headerVariants as $h) {
                if (isset($recording['request']['headers'][$h])) {
                    $value = $recording['request']['headers'][$h];
                    if (is_array($value)) {
                        $value = $value[0] ?? '';
                    }

                    // Check if it's not already filtered
                    if ($value !== '[FILTERED]' && $value !== '') {
                        $recording['request']['headers'][$h] = ['[FILTERED]'];
                        $modified = true;
                    }
                }
            }
        }
    }

    if ($modified) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($file, $json . "\n");
        echo "Scrubbed: " . basename($file) . "\n";
        $scrubbed++;
    }
}

if ($scrubbed > 0) {
    echo "\nScrubbed $scrubbed cassette(s).\n";
} else {
    echo "No cassettes needed scrubbing.\n";
}
