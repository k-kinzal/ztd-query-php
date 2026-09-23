<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Seed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Seed\CoverageSeed;
use SqlFaker\Generation\Seed\SeedCorpus;

#[CoversClass(SeedCorpus::class)]
#[UsesClass(CoverageSeed::class)]
final class SeedCorpusTest extends TestCase
{
    public function testReachedKeepsTargetLabelsInGrammarOrder(): void
    {
        $corpus = new SeedCorpus(
            'stmt',
            [new CoverageSeed("\x01", 'tail#1', 3, 'SELECT 1 AS name', ['c', 'a', 'other'], ['a']), new CoverageSeed("\x02", 'stmt#1', 1, 'DELETE', ['b'], ['b'])],
            ['a' => 'stmt#0', 'b' => 'stmt#1', 'c' => 'tail#1', 'd' => 'tail#0'],
        );

        self::assertSame(['stmt#0', 'stmt#1', 'tail#1'], $corpus->reached());
    }

    public function testEmittedKeepsOnlyTargetsPreservedInSomeOutput(): void
    {
        $corpus = new SeedCorpus(
            'stmt',
            [new CoverageSeed("\x01", 'tail#1', 3, 'SELECT 1 AS name', ['c', 'a'], ['a']), new CoverageSeed("\x02", 'stmt#1', 1, 'DELETE', ['b'], [])],
            ['a' => 'stmt#0', 'b' => 'stmt#1', 'c' => 'tail#1', 'd' => 'tail#0'],
        );

        self::assertSame(['stmt#0'], $corpus->emitted());
    }

    public function testUnreachedListsTargetsNoSeedSelected(): void
    {
        $corpus = new SeedCorpus(
            'stmt',
            [new CoverageSeed("\x01", 'tail#1', 3, 'SELECT 1 AS name', ['c', 'a'], ['a'])],
            ['a' => 'stmt#0', 'b' => 'stmt#1', 'c' => 'tail#1', 'd' => 'tail#0'],
            ['stmt#1' => 'No walk of at most 1 expansions reached the production.'],
        );

        self::assertSame(['stmt#1', 'tail#0'], $corpus->unreached());
        self::assertSame(['stmt#1' => 'No walk of at most 1 expansions reached the production.'], $corpus->failures);
    }

    public function testMaximumBudgetAnswersTheLargestBudgetOrZeroWithoutSeeds(): void
    {
        $seeds = [new CoverageSeed("\x01", 'tail#1', 3, 'SELECT 1 AS name', [], []), new CoverageSeed("\x02", 'stmt#1', 12, 'DELETE', [], [])];

        self::assertSame(12, (new SeedCorpus('stmt', $seeds, []))->maximumBudget());
        self::assertSame(0, (new SeedCorpus('stmt', [], []))->maximumBudget());
    }

    public function testCoveredIgnoresIdsOutsideTheTargets(): void
    {
        $corpus = new SeedCorpus('stmt', [], ['a' => 'stmt#0', 'b' => 'stmt#1']);

        self::assertSame(['stmt#1'], $corpus->covered(['b', 'unknown']));
        self::assertSame([], $corpus->covered([]));
    }
}
