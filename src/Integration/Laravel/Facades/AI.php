<?php

declare(strict_types=1);

namespace PHPLLM\Integration\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PHPLLM\Core\Chat;
use PHPLLM\Core\Embedding;
use PHPLLM\Core\Image;

/**
 * Laravel facade for PHPLLM.
 *
 * @method static Chat chat(?string $model = null, ?string $provider = null)
 * @method static Embedding|array embed(string|array $input, ?string $model = null, array $options = [])
 * @method static Image paint(string $prompt, ?string $model = null, array $options = [])
 *
 * @see \PHPLLM\PHPLLM
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'phpllm';
    }
}
