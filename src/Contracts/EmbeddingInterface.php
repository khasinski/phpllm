<?php

declare(strict_types=1);

namespace PHPLLM\Contracts;

use PHPLLM\Core\Embedding;

/**
 * Interface for providers that support embeddings.
 */
interface EmbeddingInterface
{
    /**
     * Generate embeddings for text.
     *
     * @param string|array<string> $input Single text or array of texts
     * @param array<string, mixed> $options
     * @return Embedding|array<Embedding>
     */
    public function embed(string|array $input, array $options = []): Embedding|array;
}
