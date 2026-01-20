<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Represents message content with optional attachments.
 *
 * Content can be text-only or multimodal (text + images/PDFs/audio).
 */
final class Content
{
    /**
     * @param array<Attachment> $attachments
     */
    public function __construct(
        public readonly ?string $text = null,
        public readonly array $attachments = [],
    ) {
    }

    /**
     * Create content from a string.
     */
    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * Create content with attachments.
     *
     * @param string|array<string>|Attachment|array<Attachment> $with
     */
    public static function with(string $text, string|array|Attachment $with): self
    {
        $attachments = self::normalizeAttachments($with);
        return new self(text: $text, attachments: $attachments);
    }

    /**
     * Check if content has attachments.
     */
    public function hasAttachments(): bool
    {
        return count($this->attachments) > 0;
    }

    /**
     * Check if content is multimodal.
     */
    public function isMultimodal(): bool
    {
        return $this->hasAttachments();
    }

    /**
     * Get only image attachments.
     *
     * @return array<Attachment>
     */
    public function getImages(): array
    {
        return array_filter($this->attachments, fn (Attachment $a) => $a->isImage());
    }

    /**
     * Get only PDF attachments.
     *
     * @return array<Attachment>
     */
    public function getPdfs(): array
    {
        return array_filter($this->attachments, fn (Attachment $a) => $a->isPdf());
    }

    /**
     * Get plain text representation.
     */
    public function toString(): string
    {
        return $this->text ?? '';
    }

    /**
     * @param string|array<string>|Attachment|array<Attachment> $with
     * @return array<Attachment>
     */
    private static function normalizeAttachments(string|array|Attachment $with): array
    {
        if ($with instanceof Attachment) {
            return [$with];
        }

        if (is_string($with)) {
            return [self::attachmentFromString($with)];
        }

        return array_map(
            fn ($item) => $item instanceof Attachment ? $item : self::attachmentFromString($item),
            $with,
        );
    }

    private static function attachmentFromString(string $value): Attachment
    {
        // Check if it's a URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return Attachment::fromUrl($value);
        }

        // Assume it's a file path
        return Attachment::fromPath($value);
    }
}
