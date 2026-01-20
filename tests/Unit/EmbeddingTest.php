<?php

declare(strict_types=1);

namespace PHPLLM\Tests\Unit;

use PHPLLM\Core\Embedding;
use PHPLLM\Tests\TestCase;

class EmbeddingTest extends TestCase
{
    public function testCosineSimilarityIdentical(): void
    {
        $vector = [0.1, 0.2, 0.3, 0.4, 0.5];

        $embedding1 = new Embedding($vector, count($vector));
        $embedding2 = new Embedding($vector, count($vector));

        $similarity = $embedding1->similarity($embedding2);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function testCosineSimilarityOrthogonal(): void
    {
        $embedding1 = new Embedding([1, 0, 0], 3);
        $embedding2 = new Embedding([0, 1, 0], 3);

        $similarity = $embedding1->similarity($embedding2);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function testCosineSimilarityOpposite(): void
    {
        $embedding1 = new Embedding([1, 0, 0], 3);
        $embedding2 = new Embedding([-1, 0, 0], 3);

        $similarity = $embedding1->similarity($embedding2);

        $this->assertEqualsWithDelta(-1.0, $similarity, 0.0001);
    }

    public function testEuclideanDistance(): void
    {
        $embedding1 = new Embedding([0, 0, 0], 3);
        $embedding2 = new Embedding([3, 4, 0], 3);

        $distance = $embedding1->distance($embedding2);

        $this->assertEqualsWithDelta(5.0, $distance, 0.0001);
    }

    public function testDimensionMismatchThrowsException(): void
    {
        $embedding1 = new Embedding([1, 2, 3], 3);
        $embedding2 = new Embedding([1, 2], 2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimension mismatch');

        $embedding1->similarity($embedding2);
    }

    public function testToArray(): void
    {
        $vector = [0.1, 0.2, 0.3];
        $embedding = new Embedding($vector, 3, 'text-embedding-3-small');

        $array = $embedding->toArray();

        $this->assertEquals($vector, $array['vector']);
        $this->assertEquals(3, $array['dimensions']);
        $this->assertEquals('text-embedding-3-small', $array['model']);
    }
}
