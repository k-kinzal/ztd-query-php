<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Seed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Derivation\TerminationCost;
use SqlFaker\Generation\Seed\ProductionGraph;
use SqlFaker\Generation\Seed\ProductionGuide;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(ProductionGuide::class)]
#[UsesClass(ProductionGraph::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
final class ProductionGuideTest extends TestCase
{
    public function testChooseDescendsTowardTheTargetAndFinishesEverythingElseCheaply(): void
    {
        $select = new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]);
        $delete = new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]);
        $one = new Production([new Terminal('1')]);
        $sum = new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]);
        $empty = new Production([]);
        $alias = new Production([new Terminal('AS'), new Terminal('name')]);
        $graph = new ProductionGraph(new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [$select, $delete]),
            'expr' => new ProductionRule('expr', [$one, $sum]),
            'tail' => new ProductionRule('tail', [$empty, $alias]),
        ]));
        $guide = new ProductionGuide($graph, 'stmt', 'tail', $alias);

        self::assertSame(0, $guide->choose(2, [$select, $delete]));
        self::assertSame(0, $guide->choose(2, [$one, $sum]));
        self::assertFalse($guide->reached());
        self::assertSame(1, $guide->choose(2, [$empty, $alias]));
        self::assertTrue($guide->reached());
        self::assertSame([$select, $one, $alias], $guide->productions());
    }

    public function testChooseFinishesCheaplyOnceTheTargetWasSelected(): void
    {
        $select = new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('expr')]);
        $one = new Production([new Terminal('1')]);
        $sum = new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]);
        $graph = new ProductionGraph(new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [$select]),
            'expr' => new ProductionRule('expr', [$one, $sum]),
        ]));
        $guide = new ProductionGuide($graph, 'stmt', 'expr', $sum);

        self::assertSame(0, $guide->choose(1, [$select]));
        self::assertSame(1, $guide->choose(2, [$one, $sum]));
        self::assertSame(0, $guide->choose(2, [$one, $sum]));
        self::assertSame(0, $guide->choose(2, [$one, $sum]));
        self::assertSame(0, $guide->choose(2, [$one, $sum]));
        self::assertSame([$select, $sum, $one, $one, $one], $guide->productions());
    }

    public function testTowardTakesTheOfferedTargetOrTheCandidateHoldingTheNearestSymbol(): void
    {
        $delete = new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]);
        $select = new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]);
        $one = new Production([new Terminal('1')]);
        $empty = new Production([]);
        $alias = new Production([new Terminal('AS'), new Terminal('name')]);
        $graph = new ProductionGraph(new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [$delete, $select]),
            'expr' => new ProductionRule('expr', [$one]),
            'tail' => new ProductionRule('tail', [$empty, $alias]),
        ]));
        $guide = new ProductionGuide($graph, 'stmt', 'tail', $alias);

        self::assertNull($guide->toward('stmt', [$delete]));
        self::assertSame([1, 2], $guide->toward('stmt', [$delete, $select]));
        self::assertSame([0, null], $guide->toward('tail', [$alias]));
        self::assertTrue($guide->reached());
    }

    public function testReachedStaysFalseUntilTheTargetIsSelected(): void
    {
        $empty = new Production([]);
        $alias = new Production([new Terminal('AS'), new Terminal('name')]);
        $graph = new ProductionGraph(new Grammar('tail', ['tail' => new ProductionRule('tail', [$empty, $alias])]));
        $guide = new ProductionGuide($graph, 'tail', 'tail', $empty);

        self::assertFalse($guide->reached());
        self::assertSame(0, $guide->choose(2, [$empty, $alias]));
        self::assertTrue($guide->reached());
    }

    public function testProductionsListsChoicesInDerivationOrder(): void
    {
        $empty = new Production([]);
        $alias = new Production([new Terminal('AS'), new Terminal('name')]);
        $graph = new ProductionGraph(new Grammar('tail', ['tail' => new ProductionRule('tail', [$empty, $alias])]));
        $guide = new ProductionGuide($graph, 'tail', 'tail', $alias);

        self::assertSame([], $guide->productions());
        $guide->choose(2, [$empty, $alias]);
        self::assertSame([$alias], $guide->productions());
    }
}
