<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param array{int, int} $left
     * @param array{int, int} $right
     * @param array{int, int} $expected
     */
    #[DataProvider('providerCosts')]
    public function testCombinePreservesEmptyAndNonEmptyCompletions(array $left, array $right, array $expected): void
    {
        self::assertSame($expected, CompletionCosts::combine($left, $right));
        self::assertSame($expected, CompletionCosts::combine($right, $left));
    }

    /**
     * @return iterable<string, array{array{int, int}, array{int, int}, array{int, int}}>
     */
    public static function providerCosts(): iterable
    {
        yield 'empty pair' => [[0, PHP_INT_MAX], [0, PHP_INT_MAX], [0, PHP_INT_MAX]];
        yield 'empty plus output' => [[2, PHP_INT_MAX], [PHP_INT_MAX, 3], [PHP_INT_MAX, 5]];
        yield 'nullable pair' => [[2, 5], [7, 3], [9, 5]];
        yield 'cheaper nonempty left' => [[8, 2], [1, 9], [9, 3]];
        yield 'unreachable' => [[PHP_INT_MAX, PHP_INT_MAX], [0, 1], [PHP_INT_MAX, PHP_INT_MAX]];
        yield 'saturating' => [[PHP_INT_MAX - 1, PHP_INT_MAX - 1], [2, 3], [PHP_INT_MAX, PHP_INT_MAX]];
    }

    public function testHasTerminalOutputDistinguishesEmittedTerminalsFromMarkersAndPendingRules(): void
    {
        $costs = new CompletionCosts(new Grammar('s', ['s' => new ProductionRule('s', [new Production([new Terminal('X')])])]), static fn (string $name): bool => $name === 'EOF');
        self::assertFalse($costs->hasTerminalOutput([]));
        self::assertFalse($costs->hasTerminalOutput([new Terminal('EOF')]));
        self::assertFalse($costs->hasTerminalOutput([new NonTerminal('s')]));
        self::assertTrue($costs->hasTerminalOutput([new Terminal('EOF'), new Terminal('X')]));
        self::assertTrue($costs->hasTerminalOutput([new Terminal('X'), new Terminal('EOF')]));
    }
}
