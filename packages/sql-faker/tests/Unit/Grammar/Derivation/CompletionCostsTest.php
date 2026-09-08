<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(CompletionCosts::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
final class CompletionCostsTest extends TestCase
{
    public function testRuleFindsFiniteCompletionsThroughNullableRecursion(): void
    {
        $grammar = new Grammar('s', ['s' => new ProductionRule('s', [new Production([]), new Production([new NonTerminal('s'), new Terminal('X')])])]);
        $costs = new CompletionCosts($grammar, static fn (string $name): bool => false);
        self::assertSame(1, $costs->rule('s', false));
        self::assertSame(2, $costs->rule('s', true));
        self::assertSame(PHP_INT_MAX, $costs->rule('missing', true));
    }

    public function testSequenceCountsMarkersAsEmptyAndReservesAllSiblings(): void
    {
        $grammar = new Grammar('s', ['s' => new ProductionRule('s', [new Production([new Terminal('X')])])]);
        $costs = new CompletionCosts($grammar, static fn (string $name): bool => $name === 'EOF');
        self::assertSame([0, PHP_INT_MAX], $costs->sequence([new Terminal('EOF')]));
        self::assertSame([PHP_INT_MAX, 2], $costs->sequence([new NonTerminal('s'), new NonTerminal('s')]));
    }

    public function testCompletionAllowsAnEmptyProductionWhenItsSiblingEmitsOutput(): void
    {
        $costs = new CompletionCosts(new Grammar('s', []), static fn (string $name): bool => false);
        self::assertSame(0, $costs->completion(new Production([]), [new Terminal('X')], true));
        self::assertSame(PHP_INT_MAX, $costs->completion(new Production([]), [], true));
    }

    public function testAffordableRetainsExactlyTheAlternativesThatCanFinishWithinBudget(): void
    {
        $empty = new Production([]);
        $nested = new Production([new NonTerminal('s')]);
        $grammar = new Grammar('s', ['s' => new ProductionRule('s', [new Production([new Terminal('X')])])]);
        $costs = new CompletionCosts($grammar, static fn (string $name): bool => false);
        self::assertSame([$empty], $costs->affordable([$empty, $nested], [new Terminal('X')], true, 0));
        self::assertSame([$nested], $costs->affordable([$empty, $nested], [], true, 1));
    }

    public function testAddSaturatesUnreachableCostsWithoutIntegerOverflow(): void
    {
        self::assertSame(PHP_INT_MAX, CompletionCosts::add(PHP_INT_MAX, 1));
        self::assertSame(7, CompletionCosts::add(3, 4));
    }
}
