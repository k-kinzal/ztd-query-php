<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\BooleanInput;
use MySqlMemory\Evaluation\FullText\BooleanSearch;
use MySqlMemory\Evaluation\FullText\Collection;
use MySqlMemory\Evaluation\FullText\Document;
use MySqlMemory\Evaluation\FullText\Term;
use MySqlMemory\Evaluation\FullText\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(BooleanSearch::class)]
#[Small]
final class BooleanSearchTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, list<float>}>
     */
    public static function providerScores(): iterable
    {
        yield 'required InnoDB' => ['+apple +banana', false, [0.5459084510803223, 0.3182637691497803, 0.0]];
        yield 'excluded InnoDB' => ['+apple -orange', false, [0.0, 0.22764469683170319, 0.0]];
        yield 'opposing weights InnoDB' => ['<apple >banana', false, [0.5459084510803223, 0.3182637691497803, 1.0906190872192383]];
        yield 'noise InnoDB' => ['apple ~banana', false, [-0.4540915787220001, -0.6817362308502197, 0.0]];
        yield 'phrase InnoDB' => ['"banana apple"', false, [0.0, 0.0, 0.0]];
        yield 'required MyISAM' => ['+apple +banana', true, [1.0, 1.0, 0.0]];
        yield 'optional MyISAM' => ['+apple banana', true, [1.3333333730697632, 1.3333333730697632, 0.0]];
        yield 'noise MyISAM' => ['apple ~banana', true, [0.5, 0.5, 0.0]];
    }

    /**
     * @param list<float> $expected
     */
    #[DataProvider('providerScores')]
    public function testScoreMatchesObservedBooleanRelevance(string $text, bool $myIsam, array $expected): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'), $myIsam ? 4 : 3);
        $documents = array_map(static fn (string $text): Document => new Document($words->split($text, Charset::known('utf8mb4')), $words), ['apple apple banana orange', 'apple banana pear', 'banana cherry pear pear', 'cherry grape', '', 'the a is x']);
        $collection = new Collection($documents);
        $search = new BooleanSearch(BooleanInput::read($text, Charset::known('utf8mb4'), $words, $myIsam), $words, $myIsam);
        self::assertSame($expected, array_map(static fn (Document $document): float => $search->score($document, $collection), array_slice($documents, 0, 3)));
    }

    public function testFrequenciesCountsRepeatedWordsAcrossGroups(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $terms = BooleanInput::read('apple +(apple pear)', Charset::known('utf8mb4'), $words, false);
        self::assertSame([$words->key('apple') => 2, $words->key('pear') => 1], (new BooleanSearch($terms, $words, false))->frequencies($terms, new Collection([])));
    }

    public function testGroupRequiresEveryRequiredTerm(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $terms = BooleanInput::read('+apple +pear', Charset::known('utf8mb4'), $words, false);
        $document = new Document(['apple'], $words);
        $seen = [];
        self::assertSame([false, 0.0], (new BooleanSearch($terms, $words, false))->group($terms, $document, new Collection([$document]), [$words->key('apple') => 1, $words->key('pear') => 1], $seen));
    }

    public function testValueKeepsMembershipSeparateFromPreviouslyCountedRelevance(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $document = new Document(['apple'], $words);
        $seen = [$words->key('apple') => true];
        self::assertSame([true, 0.0], (new BooleanSearch([], $words, false))->value(new Term(['apple']), $document, new Collection([$document]), [$words->key('apple') => 1], $seen));
    }

    public function testWeightedUsesEngineSpecificRelevanceScales(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        self::assertSame(1.5, (new BooleanSearch([], $words, true))->weighted(1.0, '>'));
        self::assertSame(-0.5, (new BooleanSearch([], $words, true))->weighted(1.0, '~'));
        self::assertSame(1.25, (new BooleanSearch([], $words, false))->weighted(0.25, '>'));
        self::assertSame(-0.75, (new BooleanSearch([], $words, false))->weighted(0.25, '~'));
    }
}
