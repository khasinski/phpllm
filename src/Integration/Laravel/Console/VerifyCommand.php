<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Console;

use Illuminate\Console\Command;
use PHPLLM\Core\Configuration;
use PHPLLM\PHPLLM;

class VerifyCommand extends Command
{
    protected $signature = 'phpllm:verify';

    protected $description = 'Verify PHPLLM configuration and test provider connections';

    public function handle(): int
    {
        $this->info('PHPLLM Configuration Verification');
        $this->newLine();

        $config = Configuration::getInstance();
        $hasErrors = false;

        // Check OpenAI
        $openaiKey = $config->getOpenaiApiKey();
        if ($openaiKey) {
            $this->line('OpenAI API Key: <fg=green>Configured</>');
            $this->testProvider('openai', 'gpt-4o-mini');
        } else {
            $this->line('OpenAI API Key: <fg=yellow>Not configured</>');
        }

        // Check Anthropic
        $anthropicKey = $config->getAnthropicApiKey();
        if ($anthropicKey) {
            $this->line('Anthropic API Key: <fg=green>Configured</>');
            $this->testProvider('anthropic', 'claude-sonnet-4-20250514');
        } else {
            $this->line('Anthropic API Key: <fg=yellow>Not configured</>');
        }

        // Check Gemini
        $geminiKey = $config->getGeminiApiKey();
        if ($geminiKey) {
            $this->line('Gemini API Key: <fg=green>Configured</>');
        } else {
            $this->line('Gemini API Key: <fg=yellow>Not configured</>');
        }

        $this->newLine();

        // Show defaults
        $this->line('Default Model: ' . ($config->getDefaultModel() ?? 'gpt-4o-mini'));
        $this->line('Request Timeout: ' . $config->getRequestTimeout() . 's');
        $this->line('Max Retries: ' . $config->getMaxRetries());

        if (!$openaiKey && !$anthropicKey && !$geminiKey) {
            $this->newLine();
            $this->error('No API keys configured! Add them to your .env file:');
            $this->line('  OPENAI_API_KEY=sk-...');
            $this->line('  ANTHROPIC_API_KEY=sk-ant-...');
            return self::FAILURE;
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    private function testProvider(string $provider, string $model): void
    {
        try {
            $chat = PHPLLM::chat($model);
            $response = $chat->ask('Say "ok" and nothing else.');

            if (str_contains(strtolower($response->getText()), 'ok')) {
                $this->line('  Connection test: <fg=green>Passed</>');
            } else {
                $this->line('  Connection test: <fg=green>Connected</> (unexpected response)');
            }
        } catch (\Exception $e) {
            $this->line('  Connection test: <fg=red>Failed</> - ' . $e->getMessage());
        }
    }
}
