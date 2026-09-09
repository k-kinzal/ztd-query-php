<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\LexicalObservation::class)]
final class GrammarCoverageInventoryTest extends TestCase
{
    public function testReachableRulesIncludesRecursionAndEmptyAlternativesButSeparatesUnrelatedRules(): void
    {
        $inventory = CoverageFixture::inventory();
        self::assertSame(['stmt' => true, 'expr' => true], $inventory->reachableRules());
        self::assertCount(5, $inventory->denominator);
        self::assertCount(6, $inventory->entries);
    }

    public function testIdKeepsOriginalOrdinalAfterFilteringAndChangesForRewrittenRhs(): void
    {
        $inventory = CoverageFixture::inventory();
        $p = new Production([new Terminal('A')], 7, 'original#7');
        self::assertSame($inventory->id('stmt', $p, 1), $inventory->id('stmt', $p, 3));
        self::assertNotSame($inventory->id('stmt', $p, 1), $inventory->id('stmt', new Production([new Terminal('B')], 7, 'original#7'), 1));
    }

    public function testRhsDistinguishesTheSymbolKindAndIncludesEmptyProduction(): void
    {
        self::assertSame([], GrammarCoverageInventory::rhs(new Production([])));
        self::assertSame(['T:SELECT', 'N:expr'], GrammarCoverageInventory::rhs(CoverageFixture::grammar()->ruleMap['stmt']->alternatives[0]));
    }

    public function testDifferencesRecordsBothRemovedAndTransformedOriginalProductions(): void
    {
        $original = (new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')]), new Production([new Terminal('B')])])]))->identified();
        $effective = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('C')], 1, 'stmt#1')])]);
        $inventory = new GrammarCoverageInventory($effective, 'stmt', 'test-v1', $original);
        self::assertSame([['origin' => 'stmt#0', 'status' => 'excluded'], ['origin' => 'stmt#1', 'status' => 'transformed']], $inventory->adaptations);
    }

    public function testInventoryEntriesRetainOriginalIdentityAndExcludeUnreachableRulesFromTheDenominator(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')], 7, 'original#7'), new Production([])]),
            'outside' => new ProductionRule('outside', [new Production([new Terminal('T')])]),
        ]);
        $inventory = new GrammarCoverageInventory($grammar, 'stmt', 'profile-a');
        self::assertSame([
            ['rule' => 'stmt', 'ordinal' => 7, 'origin' => 'original#7', 'rhs' => ['T:T'], 'rootReachable' => true],
            ['rule' => 'stmt', 'ordinal' => 1, 'origin' => null, 'rhs' => [], 'rootReachable' => true],
            ['rule' => 'outside', 'ordinal' => 0, 'origin' => null, 'rhs' => ['T:T'], 'rootReachable' => false],
        ], array_values($inventory->entries));
        self::assertSame(array_slice(array_keys($inventory->entries), 0, 2), $inventory->denominator);
        self::assertSame([], $inventory->differences($grammar));
        self::assertNotSame($inventory->fingerprint, (new GrammarCoverageInventory($grammar, 'stmt', 'profile-b'))->fingerprint);
        self::assertNotSame($inventory->fingerprint, (new GrammarCoverageInventory($grammar, 'outside', 'profile-a'))->fingerprint);
        self::assertSame($inventory->fingerprint, (new GrammarCoverageInventory($grammar, 'stmt', 'profile-a'))->fingerprint);
    }

    public function testProductionIdsDistinguishRulesOrdinalsAndGrammarProfiles(): void
    {
        $inventory = CoverageFixture::inventory();
        $production = new Production([]);
        $first = $inventory->id('stmt', $production, 0);
        self::assertNotSame($first, $inventory->id('expr', $production, 0));
        self::assertNotSame($first, $inventory->id('stmt', $production, 1));
        self::assertNotSame($first, (new GrammarCoverageInventory(CoverageFixture::grammar(), 'stmt', 'another'))->id('stmt', $production, 0));
        self::assertStringStartsWith($inventory->fingerprint . ':stmt#0:', $first);
    }
    public function testReachableRulesCanInspectAPlanRootWithoutChangingTheInventory(): void
    {
        $inventory = CoverageFixture::inventory();
        self::assertSame(['expr' => true], $inventory->reachableRules('expr'));
        self::assertSame('stmt', $inventory->root);
        self::assertArrayHasKey('stmt', $inventory->reachableRules());
    }
}
