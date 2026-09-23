<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Seed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Derivation\TerminationCost;
use SqlFaker\Generation\Seed\ProductionGraph;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(ProductionGraph::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
final class ProductionGraphTest extends TestCase
{
    public function testDistancesCountsExpansionsToTheRuleAndOmitsRulesThatCannotReachIt(): void
    {
        $graph = new ProductionGraph(new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                new Production([new Terminal('DELETE')]),
                new Production([new NonTerminal('loop')]),
            ]),
            'expr' => new ProductionRule('expr', [new Production([new Terminal('1')]), new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')])]),
            'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new NonTerminal('expr')])]),
            'loop' => new ProductionRule('loop', [new Production([new NonTerminal('loop')])]),
        ]));

        self::assertSame(['tail' => 0, 'stmt' => 1], $graph->distances('tail'));
        self::assertSame(['expr' => 0, 'stmt' => 1, 'tail' => 1], $graph->distances('expr'));
        self::assertSame(['stmt' => 0], $graph->distances('stmt'));
        self::assertSame(['loop' => 0], $graph->distances('loop'));
    }

    public function testCheapestPrefersFewerExpansionsAndThenFewerTokens(): void
    {
        $select = new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]);
        $delete = new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]);
        $one = new Production([new Terminal('1')]);
        $sum = new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]);
        $alias = new Production([new Terminal('AS'), new Terminal('name')]);
        $empty = new Production([]);
        $graph = new ProductionGraph(new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [$select, $delete]),
            'expr' => new ProductionRule('expr', [$one, $sum]),
            'tail' => new ProductionRule('tail', [$alias, $empty]),
        ]));

        self::assertSame(1, $graph->cheapest([$select, $delete]));
        self::assertSame(0, $graph->cheapest([$one, $sum]));
        self::assertSame(1, $graph->cheapest([$alias, $empty]));
    }
}
