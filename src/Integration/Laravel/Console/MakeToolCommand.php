<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Console;

use Illuminate\Console\GeneratorCommand;

class MakeToolCommand extends GeneratorCommand
{
    protected $signature = 'make:tool {name : The name of the tool class}';

    protected $description = 'Create a new PHPLLM Tool class';

    protected $type = 'Tool';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/tool.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Tools';
    }
}
