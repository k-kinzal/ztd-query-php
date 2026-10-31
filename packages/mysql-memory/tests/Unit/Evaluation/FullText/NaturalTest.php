<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\Collection;
use MySqlMemory\Evaluation\FullText\Document;
use MySqlMemory\Evaluation\FullText\Natural;
use MySqlMemory\Evaluation\FullText\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Natural::class)]
#[Small]
final class NaturalTest extends TestCase
{
    public function testReadPreservesQuotedPhrasesOnlyForInnoDb(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        self::assertSame([['apple', 'banana'], ['pear']], Natural::read('"apple banana" pear', Charset::known('utf8mb4'), $words, false)->terms);
        self::assertSame([['apple'], ['banana'], ['pear']], Natural::read('"apple banana" pear', Charset::known('utf8mb4'), $words, true)->terms);
    }

    public function testFrequenciesCountsRepeatedWordsAcrossPhraseBoundaries(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        self::assertSame([$words->key('apple') => 2, $words->key('banana') => 1], Natural::read('apple "apple banana"', Charset::known('utf8mb4'), $words, false)->frequencies());
    }

    public function testMatchingRequiresEveryPositionOfEachQuotedPhrase(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $query = Natural::read('"apple banana" pear', Charset::known('utf8mb4'), $words, false);
        self::assertSame([$words->key('pear') => 1], $query->matching(new Document(['banana', 'apple', 'pear'], $words)));
    }

    public function testScoreKeepsDuplicateCountsAndPhraseQualification(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $document = new Document(['apple', 'apple', 'banana'], $words);
        $collection = new Collection([$document, new Document(['missing'], $words), new Document([], $words)]);
        self::assertSame(0.0, Natural::read('"banana apple"', Charset::known('utf8mb4'), $words, false)->score($document, $collection, false));
        self::assertSame(0.45528939366340637, Natural::read('apple', Charset::known('utf8mb4'), $words, false)->score($document, $collection, false));
    }

    public function testExpansionAddsTermsFromMatchingDocuments(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $collection = new Collection([new Document(['apple', 'banana'], $words), new Document(['pear'], $words)]);
        self::assertSame([$words->key('banana') => 1], Natural::read('apple', Charset::known('utf8mb4'), $words, false)->expansion($collection));
        self::assertSame([$words->key('apple') => 1, $words->key('banana') => 1], Natural::read('apple', Charset::known('utf8mb4'), $words, true)->expansion($collection));
    }
}
