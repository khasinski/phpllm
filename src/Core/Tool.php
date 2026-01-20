<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use PHPLLM\Contracts\ToolInterface;

/**
 * Base class for creating tools.
 *
 * Extend this class to create tools that the LLM can call.
 *
 * Example:
 * ```php
 * class Weather extends Tool
 * {
 *     protected string $name = 'get_weather';
 *     protected string $description = 'Get current weather for a location';
 *
 *     protected function parameters(): array
 *     {
 *         return [
 *             'location' => [
 *                 'type' => 'string',
 *                 'description' => 'City name',
 *                 'required' => true,
 *             ],
 *             'unit' => [
 *                 'type' => 'string',
 *                 'enum' => ['celsius', 'fahrenheit'],
 *                 'default' => 'celsius',
 *             ],
 *         ];
 *     }
 *
 *     public function execute(array $arguments): mixed
 *     {
 *         $location = $arguments['location'];
 *         // ... fetch weather
 *         return ['temp' => 20, 'unit' => 'celsius'];
 *     }
 * }
 * ```
 */
abstract class Tool implements ToolInterface
{
    protected string $name = '';
    protected string $description = '';

    public function getName(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }

        // Generate name from class name
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Define parameters for this tool.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function parameters(): array
    {
        return [];
    }

    /**
     * Get the parameter schema in JSON Schema format.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        $params = $this->parameters();

        if (empty($params)) {
            return [
                'type' => 'object',
                'properties' => new \stdClass(),
            ];
        }

        $properties = [];
        $required = [];

        foreach ($params as $name => $config) {
            $property = [
                'type' => $config['type'] ?? 'string',
            ];

            if (isset($config['description'])) {
                $property['description'] = $config['description'];
            }

            if (isset($config['enum'])) {
                $property['enum'] = $config['enum'];
            }

            if (isset($config['default'])) {
                $property['default'] = $config['default'];
            }

            if (isset($config['items'])) {
                $property['items'] = $config['items'];
            }

            $properties[$name] = $property;

            if ($config['required'] ?? false) {
                $required[] = $name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Convert tool to OpenAI function format.
     *
     * @return array<string, mixed>
     */
    public function toFunctionSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParameters(),
            ],
        ];
    }

    /**
     * Execute the tool with given arguments.
     *
     * @param array<string, mixed> $arguments
     * @return mixed
     */
    abstract public function execute(array $arguments): mixed;
}
