<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

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
}
