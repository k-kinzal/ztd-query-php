<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\CompletionState;
use SqlFaker\Grammar\Derivation\ConstrainedCompletion;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(ConstrainedCompletion::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\CompletionWitness::class)]
final class ConstrainedCompletionTest extends TestCase
{
    /**
     * @param GenerationPlan<bool> $plan
     */
    #[DataProvider('providerConstraints')]
    public function testMinimumIncludesDescendantsSiblingsAndRecursiveOccurrenceConstraints(GenerationPlan $plan, int $budget, bool $nonEmpty, int $expected): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf'), new NonTerminal('leaf')])]),
            'leaf' => new ProductionRule('leaf', [new Production([]), new Production([new NonTerminal('end')]), new Production([new NonTerminal('leaf')])]),
            'end' => new ProductionRule('end', [new Production([new Terminal('T')])]),
        ]);
        $completion = new ConstrainedCompletion($grammar, new CompletionCosts($grammar, static fn (string $name): bool => false));
        self::assertSame($expected, $completion->minimum([new NonTerminal('root')], $plan, [], $nonEmpty, $budget));
    }

    /**
     * @return iterable<array{GenerationPlan<bool>, int, bool, int}>
     */
    public static function providerConstraints(): iterable
    {
        yield [GenerationPlan::all(), 10, false, 3];
        yield [GenerationPlan::all(), 10, true, 4];
        $second = GenerationPlan::constrained('root', ['leaf' => [ProductionPattern::at(0), ProductionPattern::at(1)]]);
        yield [$second, 4, true, 4];
        yield [$second, 3, true, PHP_INT_MAX];
        $recursive = GenerationPlan::constrained('root', ['leaf' => [ProductionPattern::at(2), ProductionPattern::at(1), ProductionPattern::at(1)]]);
        yield [$recursive, 6, true, 6];
        yield [$recursive, 5, true, PHP_INT_MAX];
        yield [GenerationPlan::all()->withPatternForEveryOccurrence('leaf', ProductionPattern::at(2)), 20, true, PHP_INT_MAX];
        yield [GenerationPlan::all()->withPatternForEveryOccurrence('leaf', ProductionPattern::at(0)), 3, false, 3];
        yield [GenerationPlan::all()->withPatternForEveryOccurrence('leaf', ProductionPattern::at(0)), 3, true, PHP_INT_MAX];
        yield [GenerationPlan::constrained('root', ['unused' => [ProductionPattern::at(9)]]), 4, true, 4];
    }

    public function testMinimumCountsOnlyTheUnconsumedPatternSuffix(): void
    {
        $grammar = new Grammar('leaf', [
            'leaf' => new ProductionRule('leaf', [new Production([]), new Production([new Terminal('T')])]),
        ]);
        $plan = GenerationPlan::constrained('leaf', ['leaf' => [ProductionPattern::at(0), ProductionPattern::at(1)]]);
        $completion = new ConstrainedCompletion($grammar, new CompletionCosts($grammar, static fn (string $name): bool => false));
        self::assertSame(1, $completion->minimum([new NonTerminal('leaf')], $plan, ['leaf' => 1], true, 1));
        self::assertSame(PHP_INT_MAX, $completion->minimum([new NonTerminal('leaf')], $plan, [], true, 1));
    }

    public function testWithinRejectsARequiredOutputThatIndependentEmptySubtreesCannotSupply(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('free'), new NonTerminal('fixed')])]),
            'free' => new ProductionRule('free', [new Production([]), new Production([new NonTerminal('end')])]),
            'fixed' => new ProductionRule('fixed', [new Production([])]),
            'end' => new ProductionRule('end', [new Production([new Terminal('T')])]),
        ]);
        $completion = new ConstrainedCompletion($grammar, new CompletionCosts($grammar, static fn (string $name): bool => false));
        $plan = GenerationPlan::all()->withPatternForEveryOccurrence('fixed', ProductionPattern::at(0));
        self::assertFalse($completion->within([new NonTerminal('root')], $plan, [], true, 3));
        self::assertTrue($completion->within([new NonTerminal('root')], $plan, [], true, 4));
        self::assertTrue($completion->within([new NonTerminal('root')], $plan, [], false, 3));
    }

    public function testCompleteCachesNormalizedPendingFormsWithoutLosingAlreadyEmittedOutput(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([])])]);
        $completion = new ConstrainedCompletion($grammar, new CompletionCosts($grammar, static fn (string $name): bool => false));
        $plan = GenerationPlan::all();
        self::assertSame(1, $completion->complete([new Terminal('T'), new NonTerminal('leaf')], $plan, [], true, 1, true));
        self::assertSame(1, $completion->complete([new NonTerminal('leaf')], $plan, [], false, 1, true));
        self::assertSame(PHP_INT_MAX, $completion->complete([new NonTerminal('leaf')], $plan, [], true, 1, true));
    }

    public function testSearchRetainsAFirstWitnessAsFeasibilityInsteadOfAMinimumProof(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])])]);
        $completion = new ConstrainedCompletion($grammar, new CompletionCosts($grammar, static fn (string $name): bool => false));
        self::assertSame(1, $completion->search([new NonTerminal('leaf')], GenerationPlan::all(), [], true, 1, false));
    }

    public function testWitnessCachesOnlyTheChosenSuffixAndKeepsMinimumQueriesIndependent(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])])]);
        $completion = new ConstrainedCompletion($grammar, new CompletionCosts($grammar, static fn (string $name): bool => false));
        $plan = GenerationPlan::all();
        $state = new CompletionState([new NonTerminal('leaf')], [], true, 0);
        self::assertSame(3, $completion->witness(new CompletionState([], [], false, 3, $state), $plan, 3, 5));
        self::assertSame(3, $completion->complete([new NonTerminal('leaf')], $plan, [], true, 5, false));
        self::assertSame(1, $completion->minimum([new NonTerminal('leaf')], $plan, [], true, 5));
    }
}
