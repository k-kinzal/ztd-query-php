<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\CoverageSets;
use SqlFaker\Coverage\CoverageSnapshotStore;
use SqlFaker\Coverage\GenerationTrace;
use SqlFaker\Coverage\GeneratorRevision;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Coverage\LexicalObservation;
use SqlFaker\Coverage\SnapshotValidation;
use SqlFaker\Generation\Choice\ByteChoices;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\DerivationNode;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(GrammarCoverageInventory::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(CoverageException::class)]
#[UsesClass(GrammarCoverage::class)]
#[UsesClass(GeneratorRevision::class)]
#[UsesClass(CoverageSnapshotStore::class)]
#[UsesClass(SnapshotValidation::class)]
#[UsesClass(GenerationTrace::class)]
#[UsesClass(CoverageSets::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(DerivationNode::class)]
#[UsesClass(LexicalObservation::class)]
final class GrammarCoverageInventoryTest extends TestCase
{
    public function testReachableRulesIncludesRecursionAndEmptyAlternativesButSeparatesUnrelatedRules(): void
    {
        $inventory = new GrammarCoverageInventory(
            (new Grammar(
                'stmt',
                [
                    'stmt' => new ProductionRule(
                        'stmt',
                        [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                    ),
                    'expr' => new ProductionRule(
                        'expr',
                        [
                            new Production([new Terminal('1')]),
                            new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                            new Production([]),
                        ],
                    ),
                    'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                ],
            ))->identified(),
            'stmt',
            'test-v1',
        );
        self::assertSame(['stmt' => true, 'expr' => true], $inventory->reachableRules());
        self::assertCount(5, $inventory->denominator);
        self::assertCount(6, $inventory->entries);
    }

    public function testIdKeepsOriginalOrdinalAfterFilteringAndChangesForRewrittenRhs(): void
    {
        $inventory = new GrammarCoverageInventory(
            (new Grammar(
                'stmt',
                [
                    'stmt' => new ProductionRule(
                        'stmt',
                        [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                    ),
                    'expr' => new ProductionRule(
                        'expr',
                        [
                            new Production([new Terminal('1')]),
                            new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                            new Production([]),
                        ],
                    ),
                    'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                ],
            ))->identified(),
            'stmt',
            'test-v1',
        );
        $p = new Production([new Terminal('A')], 7, 'original#7');
        self::assertSame($inventory->id('stmt', $p, 1), $inventory->id('stmt', $p, 3));
        self::assertNotSame($inventory->id('stmt', $p, 1), $inventory->id('stmt', new Production([new Terminal('B')], 7, 'original#7'), 1));
    }

    public function testRhsDistinguishesTheSymbolKindAndIncludesEmptyProduction(): void
    {
        self::assertSame([], GrammarCoverageInventory::rhs(new Production([])));
        self::assertSame(
            ['T:SELECT', 'N:expr'],
            GrammarCoverageInventory::rhs(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified()->ruleMap['stmt']->alternatives[0],
            ),
        );
    }

    public function testDifferencesRecordsBothRemovedAndTransformedOriginalProductions(): void
    {
        $original = (new Grammar(
            'stmt',
            ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')]), new Production([new Terminal('B')])])],
        ))->identified();
        $effective = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('C')], 1, 'stmt#1')])]);
        $inventory = new GrammarCoverageInventory($effective, 'stmt', 'test-v1', $original);
        self::assertSame(
            [['origin' => 'stmt#0', 'status' => 'excluded'], ['origin' => 'stmt#1', 'status' => 'transformed']],
            $inventory->adaptations,
        );
    }

    public function testInventoryEntriesRetainOriginalIdentityAndExcludeUnreachableRulesFromTheDenominator(): void
    {
        $grammar = new Grammar(
            'stmt',
            [
                'stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')], 7, 'original#7'), new Production([])]),
                'outside' => new ProductionRule('outside', [new Production([new Terminal('T')])]),
            ],
        );
        $inventory = new GrammarCoverageInventory($grammar, 'stmt', 'profile-a');
        self::assertSame(
            [
                ['rule' => 'stmt', 'ordinal' => 7, 'origin' => 'original#7', 'rhs' => ['T:T'], 'rootReachable' => true],
                ['rule' => 'stmt', 'ordinal' => 1, 'origin' => null, 'rhs' => [], 'rootReachable' => true],
                ['rule' => 'outside', 'ordinal' => 0, 'origin' => null, 'rhs' => ['T:T'], 'rootReachable' => false],
            ],
            array_values($inventory->entries),
        );
        self::assertSame(array_slice(array_keys($inventory->entries), 0, 2), $inventory->denominator);
        self::assertSame([], $inventory->differences($grammar));
        self::assertNotSame($inventory->fingerprint, (new GrammarCoverageInventory($grammar, 'stmt', 'profile-b'))->fingerprint);
        self::assertNotSame($inventory->fingerprint, (new GrammarCoverageInventory($grammar, 'outside', 'profile-a'))->fingerprint);
        self::assertSame($inventory->fingerprint, (new GrammarCoverageInventory($grammar, 'stmt', 'profile-a'))->fingerprint);
    }

    public function testProductionIdsDistinguishRulesOrdinalsAndGrammarProfiles(): void
    {
        $inventory = new GrammarCoverageInventory(
            (new Grammar(
                'stmt',
                [
                    'stmt' => new ProductionRule(
                        'stmt',
                        [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                    ),
                    'expr' => new ProductionRule(
                        'expr',
                        [
                            new Production([new Terminal('1')]),
                            new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                            new Production([]),
                        ],
                    ),
                    'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                ],
            ))->identified(),
            'stmt',
            'test-v1',
        );
        $production = new Production([]);
        $first = $inventory->id('stmt', $production, 0);
        self::assertNotSame($first, $inventory->id('expr', $production, 0));
        self::assertNotSame($first, $inventory->id('stmt', $production, 1));
        self::assertNotSame(
            $first,
            (new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'another',
            ))->id('stmt', $production, 0),
        );
        self::assertStringStartsWith($inventory->fingerprint . ':stmt#0:', $first);
    }

    public function testReachableRulesCanInspectAPlanRootWithoutChangingTheInventory(): void
    {
        $inventory = new GrammarCoverageInventory(
            (new Grammar(
                'stmt',
                [
                    'stmt' => new ProductionRule(
                        'stmt',
                        [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                    ),
                    'expr' => new ProductionRule(
                        'expr',
                        [
                            new Production([new Terminal('1')]),
                            new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                            new Production([]),
                        ],
                    ),
                    'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                ],
            ))->identified(),
            'stmt',
            'test-v1',
        );
        self::assertSame(['expr' => true], $inventory->reachableRules('expr'));
        self::assertSame('stmt', $inventory->root);
        self::assertArrayHasKey('stmt', $inventory->reachableRules());
    }
}
