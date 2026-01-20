<?php

declare(strict_types=1);

namespace PHPLLM\Contracts;

use PHPLLM\Core\Image;

/**
 * Interface for providers that support image generation.
 */
interface ImageGenerationInterface
{
    /**
     * Generate an image from a prompt.
     *
     * @param array<string, mixed> $options
     */
    public function generateImage(string $prompt, array $options = []): Image;

    /**
     * Generate multiple images from a prompt.
     *
     * @param array<string, mixed> $options
     * @return array<Image>
     */
    public function generateImages(string $prompt, int $count, array $options = []): array;
}
