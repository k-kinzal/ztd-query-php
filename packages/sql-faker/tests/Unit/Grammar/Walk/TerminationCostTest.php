<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Walk;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Walk\TerminationCost;

#[CoversClass(TerminationCost::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
final class TerminationCostTest extends TestCase
{
    public function testOfCountsTheTokensOfTheCheapestAlternative(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('A'), new Terminal('B'), new Terminal('C')]),
                new Production([new Terminal('A')]),
            ]),
        ]);

        self::assertSame(1, (new TerminationCost($grammar, static fn (string $t): bool => true, 1, 0))->of('stmt'));
    }

    public function testOfCountsARuleThatCanOnlyExpandIntoItselfAsUnfinishable(): void
    {
        $grammar = new Grammar('loop', [
            'loop' => new ProductionRule('loop', [new Production([new NonTerminal('loop')])]),
        ]);

        self::assertSame(
            PHP_INT_MAX,
            (new TerminationCost($grammar, static fn (string $t): bool => true, 1, 0))->of('loop'),
        );
    }

    public function testOfTreatsASymbolWithNoRuleAsAToken(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')])])]);

        self::assertSame(1, (new TerminationCost($grammar, static fn (string $t): bool => true, 1, 0))->of('IDENT'));
    }

    public function testOfRefusesATokenNothingCanWrite(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')])])]);

        self::assertSame(
            PHP_INT_MAX,
            (new TerminationCost($grammar, static fn (string $t): bool => false, 1, 0))->of('IDENT'),
        );
    }

    public function testOfProductionAddsUpWhatEachSymbolCosts(): void
    {
        $grammar = new Grammar('nm', ['nm' => new ProductionRule('nm', [new Production([new Terminal('IDENT')])])]);
        $cost = new TerminationCost($grammar, static fn (string $t): bool => true, 1, 0);

        self::assertSame(2, $cost->ofProduction(new Production([new Terminal('SELECT'), new NonTerminal('nm')])));
    }

    public function testOfProductionCountsExpansionsWhenThatIsWhatAStepIs(): void
    {
        $grammar = new Grammar('nm', ['nm' => new ProductionRule('nm', [new Production([new Terminal('IDENT')])])]);
        $cost = new TerminationCost($grammar, static fn (string $t): bool => true, 0, 1);

        self::assertSame(1, $cost->ofProduction(new Production([new Terminal('SELECT'), new NonTerminal('nm')])));
    }

    public function testSettledAnswersTheLeastEveryRuleCanCost(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('nm')])]),
            'nm' => new ProductionRule('nm', [new Production([new Terminal('IDENT')])]),
        ]);
        $cost = new TerminationCost($grammar, static fn (string $t): bool => true, 1, 0);

        self::assertSame(['stmt' => 2, 'nm' => 1], $cost->settled($grammar));
    }

    public function testSumRefusesASequenceCarryingATokenNothingCanWrite(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')])])]);
        $cost = new TerminationCost($grammar, static fn (string $t): bool => $t !== 'B', 1, 0);

        self::assertSame(PHP_INT_MAX, $cost->sum([new Terminal('A'), new Terminal('B')], []));
    }
}
