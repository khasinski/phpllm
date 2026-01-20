<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Represents a generated image result.
 */
final class Image
{
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $base64 = null,
        public readonly ?string $revisedPrompt = null,
        public readonly ?string $model = null,
    ) {
    }

    /**
     * Check if image is available as URL.
     */
    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    /**
     * Check if image is available as base64.
     */
    public function hasBase64(): bool
    {
        return $this->base64 !== null;
    }

    /**
     * Get the image data URL for embedding in HTML.
     */
    public function getDataUrl(): ?string
    {
        if ($this->base64 === null) {
            return null;
        }

        return "data:image/png;base64,{$this->base64}";
    }

    /**
     * Save image to a file.
     */
    public function save(string $path): bool
    {
        if ($this->base64 !== null) {
            $data = base64_decode($this->base64);
            return file_put_contents($path, $data) !== false;
        }

        if ($this->url !== null) {
            $data = file_get_contents($this->url);
            if ($data === false) {
                return false;
            }
            return file_put_contents($path, $data) !== false;
        }

        return false;
    }

    /**
     * Get raw bytes of the image.
     */
    public function getBytes(): ?string
    {
        if ($this->base64 !== null) {
            return base64_decode($this->base64);
        }

        if ($this->url !== null) {
            $data = file_get_contents($this->url);
            return $data !== false ? $data : null;
        }

        return null;
    }
}
