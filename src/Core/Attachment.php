<?php

declare(strict_types=1);

namespace PHPLLM\Core;

use League\MimeTypeDetection\FinfoMimeTypeDetector;

/**
 * Represents a file attachment (image, PDF, audio, etc.).
 */
final class Attachment
{
    private ?string $mimeType = null;
    private ?string $base64Content = null;

    private function __construct(
        private readonly ?string $path = null,
        private readonly ?string $url = null,
        private readonly ?string $content = null,
        private readonly ?string $filename = null,
    ) {
    }

    /**
     * Create attachment from a file path.
     */
    public static function fromPath(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        return new self(path: $path, filename: basename($path));
    }

    /**
     * Create attachment from a URL.
     */
    public static function fromUrl(string $url): self
    {
        return new self(url: $url);
    }

    /**
     * Create attachment from raw content.
     */
    public static function fromContent(string $content, string $mimeType, ?string $filename = null): self
    {
        $attachment = new self(content: $content, filename: $filename);
        $attachment->mimeType = $mimeType;
        return $attachment;
    }

    /**
     * Create attachment from base64-encoded content.
     */
    public static function fromBase64(string $base64, string $mimeType, ?string $filename = null): self
    {
        $attachment = new self(filename: $filename);
        $attachment->base64Content = $base64;
        $attachment->mimeType = $mimeType;
        return $attachment;
    }

    /**
     * Get the MIME type of the attachment.
     */
    public function getMimeType(): string
    {
        if ($this->mimeType !== null) {
            return $this->mimeType;
        }

        if ($this->path !== null) {
            $detector = new FinfoMimeTypeDetector();
            $this->mimeType = $detector->detectMimeTypeFromFile($this->path) ?? 'application/octet-stream';
            return $this->mimeType;
        }

        if ($this->url !== null) {
            // Try to detect from URL extension
            $extension = pathinfo(parse_url($this->url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $this->mimeType = $this->mimeTypeFromExtension($extension);
            return $this->mimeType;
        }

        return 'application/octet-stream';
    }

    /**
     * Check if attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->getMimeType(), 'image/');
    }

    /**
     * Check if attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->getMimeType() === 'application/pdf';
    }

    /**
     * Check if attachment is audio.
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->getMimeType(), 'audio/');
    }

    /**
     * Check if attachment is a text file.
     */
    public function isText(): bool
    {
        $mime = $this->getMimeType();
        return str_starts_with($mime, 'text/') || in_array($mime, [
            'application/json',
            'application/xml',
            'application/javascript',
        ], true);
    }

    /**
     * Check if this is a URL-based attachment.
     */
    public function isUrl(): bool
    {
        return $this->url !== null;
    }

    /**
     * Get the URL if this is a URL-based attachment.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get the filename.
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Get the base64-encoded content.
     */
    public function getBase64(): string
    {
        if ($this->base64Content !== null) {
            return $this->base64Content;
        }

        if ($this->content !== null) {
            $this->base64Content = base64_encode($this->content);
            return $this->base64Content;
        }

        if ($this->path !== null) {
            $content = file_get_contents($this->path);
            if ($content === false) {
                throw new \RuntimeException("Failed to read file: {$this->path}");
            }
            $this->base64Content = base64_encode($content);
            return $this->base64Content;
        }

        throw new \RuntimeException('Cannot get base64 content for URL-based attachment');
    }

    /**
     * Get raw content if available.
     */
    public function getContent(): ?string
    {
        if ($this->content !== null) {
            return $this->content;
        }

        if ($this->path !== null) {
            $content = file_get_contents($this->path);
            return $content !== false ? $content : null;
        }

        if ($this->base64Content !== null) {
            $decoded = base64_decode($this->base64Content, true);
            return $decoded !== false ? $decoded : null;
        }

        return null;
    }

    /**
     * Get data URL (for embedding in HTML or certain APIs).
     */
    public function getDataUrl(): string
    {
        return "data:{$this->getMimeType()};base64,{$this->getBase64()}";
    }

    private function mimeTypeFromExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            default => 'application/octet-stream',
        };
    }
}
