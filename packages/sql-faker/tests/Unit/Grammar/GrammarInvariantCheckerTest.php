<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\GrammarInvariantChecker;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(GrammarInvariantChecker::class)]
final class GrammarInvariantCheckerTest extends TestCase
{
    public function testUndefinedReferencesGroupsMissingTargetsBySourceRule(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('expr'), new NonTerminal('missing_expr')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new NonTerminal('missing_term')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame([
            'expr' => ['missing_term'],
            'stmt' => ['missing_expr'],
        ], $checker->undefinedReferences());
    }

    public function testRulesWithoutAlternativesReturnsOnlyEmptyRules(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SELECT')]),
            ]),
            'empty_rule' => new ProductionRule('empty_rule', []),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['empty_rule'], $checker->rulesWithoutAlternatives());
    }

    public function testMissingEntryRulesReturnsUnknownEntries(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SELECT')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['missing'], $checker->missingEntryRules(['stmt', 'missing']));
    }

    public function testMissingEntryRulesDeduplicatesAndSortsUnknownEntries(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SELECT')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(
            ['missing_a', 'missing_b'],
            $checker->missingEntryRules(['missing_b', 'stmt', 'missing_a', 'missing_b']),
        );
    }

    public function testCanTerminateDistinguishesFiniteAndInfiniteRuleFamilies(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('finite')]),
            ]),
            'finite' => new ProductionRule('finite', [
                new Production([new Terminal('A')]),
            ]),
            'loop' => new ProductionRule('loop', [
                new Production([new NonTerminal('loop'), new Terminal('B')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertTrue($checker->canTerminate('stmt'));
        self::assertTrue($checker->canTerminate('finite'));
        self::assertFalse($checker->canTerminate('loop'));
    }

    public function testReachableAndUnreachableRulesAreComputedFromEntryRules(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('expr')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Terminal('A')]),
            ]),
            'unused' => new ProductionRule('unused', [
                new Production([new Terminal('B')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['expr', 'stmt'], $checker->reachableRules(['stmt']));
        self::assertSame(['unused'], $checker->unreachableRules(['stmt']));
    }

    public function testReachableRulesDeduplicateEntriesAndReturnSortedRuleNames(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('expr')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Terminal('A')]),
            ]),
            'other' => new ProductionRule('other', [
                new Production([new Terminal('B')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['expr', 'other', 'stmt'], $checker->reachableRules(['other', 'stmt', 'other']));
    }

    public function testReachableRulesIgnoreUnknownEntriesAndTerminalValues(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('other'), new NonTerminal('expr')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Terminal('A')]),
            ]),
            'other' => new ProductionRule('other', [
                new Production([new Terminal('B')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['expr', 'stmt'], $checker->reachableRules(['missing', 'stmt']));
        self::assertSame(['other'], $checker->unreachableRules(['missing', 'stmt']));
    }

    public function testNonTerminatingReachableRulesAreReportedForTheEntryClosure(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('loop')]),
            ]),
            'loop' => new ProductionRule('loop', [
                new Production([new NonTerminal('loop'), new Terminal('A')]),
            ]),
            'unused' => new ProductionRule('unused', [
                new Production([new Terminal('B')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['loop', 'stmt'], $checker->nonTerminatingReachableRules(['stmt']));
    }

    public function testNonTerminatingReachableRulesExcludeReachableTerminatingRules(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('finite')]),
                new Production([new NonTerminal('loop')]),
            ]),
            'finite' => new ProductionRule('finite', [
                new Production([new Terminal('A')]),
            ]),
            'loop' => new ProductionRule('loop', [
                new Production([new NonTerminal('loop')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['loop'], $checker->nonTerminatingReachableRules(['stmt']));
    }

    public function testConstructorRejectsMissingStringStartSymbol(): void
    {
        $grammar = new class () {
            public int $startSymbol = 1;

            /** @var array<string, object> */
            public array $ruleMap = [];
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Object property startSymbol must be a string.');
        new GrammarInvariantChecker($grammar);
    }

    public function testUndefinedReferencesDeduplicatesAndSortsTargetsPerRule(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('missing_b'), new NonTerminal('missing_a'), new NonTerminal('missing_b')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['stmt' => ['missing_a', 'missing_b']], $checker->undefinedReferences());
    }

    public function testUndefinedReferencesScansAllAlternativesOfEachRule(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('expr')]),
                new Production([new NonTerminal('missing_from_second_alternative')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Terminal('A')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame([
            'stmt' => ['missing_from_second_alternative'],
        ], $checker->undefinedReferences());
    }

    public function testRulesWithoutAlternativesReturnsSortedEmptyRuleNames(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SELECT')]),
            ]),
            'z_rule' => new ProductionRule('z_rule', []),
            'a_rule' => new ProductionRule('a_rule', []),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['a_rule', 'z_rule'], $checker->rulesWithoutAlternatives());
    }

    public function testUnreachableRulesReturnSortedAllRuleNamesOutsideEntryClosure(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('expr')]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Terminal('SELECT')]),
            ]),
            'z_rule' => new ProductionRule('z_rule', [
                new Production([new Terminal('Z')]),
            ]),
            'a_rule' => new ProductionRule('a_rule', [
                new Production([new Terminal('A')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertSame(['a_rule', 'z_rule'], $checker->unreachableRules(['stmt']));
    }

    public function testCanTerminatePropagatesAcrossLaterRulesInSubsequentPasses(): void
    {
        $grammar = new Grammar('start', [
            'a' => new ProductionRule('a', [
                new Production([new Terminal('A')]),
            ]),
            'c' => new ProductionRule('c', [
                new Production([new NonTerminal('b')]),
            ]),
            'b' => new ProductionRule('b', [
                new Production([new NonTerminal('a')]),
            ]),
            'start' => new ProductionRule('start', [
                new Production([new NonTerminal('c')]),
            ]),
        ]);

        $checker = new GrammarInvariantChecker($grammar);

        self::assertTrue($checker->canTerminate('b'));
        self::assertTrue($checker->canTerminate('c'));
        self::assertTrue($checker->canTerminate('start'));
    }

    public function testConstructorRejectsRuleMapWithNonStringKey(): void
    {
        $grammar = new class () {
            public string $startSymbol = 'stmt';

            /** @var array<int, object> */
            public array $ruleMap;

            public function __construct()
            {
                $this->ruleMap = [
                    0 => new class () {
                        /** @var list<object> */
                        public array $alternatives = [];
                    },
                ];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Object property ruleMap must be an object map.');
        new GrammarInvariantChecker($grammar);
    }

    public function testConstructorRejectsRuleMapWithNonObjectItem(): void
    {
        $grammar = new class () {
            public string $startSymbol = 'stmt';

            /** @var array<string, string> */
            public array $ruleMap = [
                'stmt' => 'invalid',
            ];
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Object property ruleMap must be an object map.');
        new GrammarInvariantChecker($grammar);
    }

    public function testRulesWithoutAlternativesRejectsAssociativeAlternativesArray(): void
    {
        $grammar = new class () {
            public string $startSymbol = 'stmt';

            /** @var array<string, object> */
            public array $ruleMap;

            public function __construct()
            {
                $this->ruleMap = [
                    'stmt' => new class () {
                        /** @var array<string, object> */
                        public array $alternatives;

                        public function __construct()
                        {
                            $this->alternatives = [
                                'named' => new class () {
                                    /** @var list<object> */
                                    public array $symbols = [];
                                },
                            ];
                        }
                    },
                ];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production rule must expose an alternatives array.');
        new GrammarInvariantChecker($grammar);
    }

    public function testUndefinedReferencesRejectsNonObjectAlternativeEntry(): void
    {
        $grammar = new class () {
            public string $startSymbol = 'stmt';

            /** @var array<string, object> */
            public array $ruleMap;

            public function __construct()
            {
                $this->ruleMap = [
                    'stmt' => new class () {
                        /** @var list<object|string> */
                        public array $alternatives = ['invalid'];
                    },
                ];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production rule must expose an alternatives array.');
        new GrammarInvariantChecker($grammar);
    }
}
