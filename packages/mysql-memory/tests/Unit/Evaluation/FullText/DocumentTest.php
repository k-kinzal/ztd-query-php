<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\Document;
use MySqlMemory\Evaluation\FullText\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Document::class)]
#[Small]
final class DocumentTest extends TestCase
{
    public function testPhraseRequiresConsecutivePositionsIncludingStopwords(): void
    {
        $document = new Document(['apple', 'the', 'banana'], new Words(Collation::known('utf8mb4_0900_ai_ci')));
        self::assertTrue($document->phrase(['APPLE', 'the', 'banana']));
        self::assertFalse($document->phrase(['apple', 'banana']));
        self::assertFalse($document->phrase([]));
    }

    public function testProximityCountsDistinctPositionsInsideAnUnorderedWindow(): void
    {
        $document = new Document(['apple', 'banana', 'orange'], new Words(Collation::known('utf8mb4_bin')));
        self::assertTrue($document->proximity(['orange', 'apple'], 3));
        self::assertFalse($document->proximity(['orange', 'apple'], 2));
        self::assertFalse($document->proximity(['apple', 'apple'], 3));
    }

    public function testFrequenciesExcludesStopwordsButCountsRepeatedTerms(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        self::assertSame([$words->key('apple') => 2], (new Document(['apple', 'the', 'apple'], $words))->frequencies());
    }
}
