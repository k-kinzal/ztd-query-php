<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\Collection;
use MySqlMemory\Evaluation\FullText\Document;
use MySqlMemory\Evaluation\FullText\Term;
use MySqlMemory\Evaluation\FullText\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Term::class)]
#[Small]
final class TermTest extends TestCase
{
    public function testFrequenciesExpandsShortPrefixesAgainstIndexedWords(): void
    {
        $words = new Words(Collation::known('utf8mb4_0900_ai_ci'));
        $collection = new Collection([new Document(['Apple', 'apple', 'banana', 'the'], $words)]);
        self::assertSame([$words->key('apple') => 1], (new Term(['ap'], prefix: true))->frequencies($words, $collection));
        self::assertSame([], (new Term(['the'], prefix: true))->frequencies($words, $collection));
    }

    public function testMatchesDistinguishesPhrasesFromUnorderedProximity(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $document = new Document(['banana', 'apple'], $words);
        $collection = new Collection([$document]);
        self::assertFalse((new Term(['apple', 'banana'], phrase: true))->matches($document, $collection));
        self::assertTrue((new Term(['apple', 'banana'], distance: 2, phrase: true))->matches($document, $collection));
    }
}
