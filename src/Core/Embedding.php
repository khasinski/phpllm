<?php

declare(strict_types=1);

namespace PHPLLM\Core;

/**
 * Represents an embedding vector result.
 */
final class Embedding
{
    /**
     * @param array<float> $vector The embedding vector
     * @param int $dimensions Number of dimensions
     */
    public function __construct(
        public readonly array $vector,
        public readonly int $dimensions,
        public readonly ?string $model = null,
        public readonly ?Tokens $tokens = null,
    ) {
    }

    /**
     * Calculate cosine similarity with another embedding.
     */
    public function similarity(self $other): float
    {
        if ($this->dimensions !== $other->dimensions) {
            throw new \InvalidArgumentException(
                "Dimension mismatch: {$this->dimensions} vs {$other->dimensions}",
            );
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $this->dimensions; $i++) {
            $dotProduct += $this->vector[$i] * $other->vector[$i];
            $normA += $this->vector[$i] ** 2;
            $normB += $other->vector[$i] ** 2;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator === 0.0) {
            return 0.0;
        }

        return $dotProduct / $denominator;
    }

    /**
     * Calculate Euclidean distance with another embedding.
     */
    public function distance(self $other): float
    {
        if ($this->dimensions !== $other->dimensions) {
            throw new \InvalidArgumentException(
                "Dimension mismatch: {$this->dimensions} vs {$other->dimensions}",
            );
        }

        $sum = 0.0;
        for ($i = 0; $i < $this->dimensions; $i++) {
            $sum += ($this->vector[$i] - $other->vector[$i]) ** 2;
        }

        return sqrt($sum);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vector' => $this->vector,
            'dimensions' => $this->dimensions,
            'model' => $this->model,
        ];
    }
}
