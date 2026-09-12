<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Derivation\Completion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\Completion\CompletionWitness;
use SqlFaker\Generation\Derivation\Completion\PatternProductions;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\CompletionMemo;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(CompletionWitness::class)]
#[UsesClass(PatternProductions::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(CompletionMemo::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionState::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
final class CompletionWitnessTest extends TestCase
{
    public function testCompleteWalksOccurrencePatternsAndPreservesPendingOutput(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([]), new Production([new Terminal('T')])])]);
        $witness = new CompletionWitness(new CompletionCosts($grammar, static fn (string $name): bool => false), new PatternProductions($grammar), new CompletionMemo());
        $plan = GenerationPlan::constrained('leaf', ['leaf' => [ProductionPattern::at(0), ProductionPattern::at(1)]]);
        self::assertSame(2, $witness->complete([new NonTerminal('leaf'), new NonTerminal('leaf')], $plan, [], true, 2));
        self::assertNull($witness->complete([new NonTerminal('leaf'), new NonTerminal('leaf')], $plan, [], true, 1));
        self::assertNull($witness->complete([new NonTerminal('leaf')], $plan, [], true, 2));
        self::assertSame(1, $witness->complete([new NonTerminal('leaf')], $plan, ['leaf' => 1], true, 1));
    }

    public function testCompleteYieldsToTheSolverWhenAGreedyPathNeedsAnotherChoice(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf')]), new Production([new Terminal('T')])]),
            'leaf' => new ProductionRule('leaf', [new Production([])]),
        ]);
        $witness = new CompletionWitness(new CompletionCosts($grammar, static fn (string $name): bool => false), new PatternProductions($grammar), new CompletionMemo());
        self::assertNull($witness->complete([new NonTerminal('root')], GenerationPlan::all()->withPatternForEveryOccurrence('root', ProductionPattern::at(0)), [], true, 10));
        self::assertSame(1, $witness->complete([new NonTerminal('root')], GenerationPlan::all(), [], true, 1));
        self::assertSame(0, $witness->complete([new Terminal('T')], GenerationPlan::all(), [], true, 0));
    }

    public function testCompleteBoundsTheOptionalProbeWithoutClaimingImpossibility(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([new NonTerminal('leaf')]), new Production([new Terminal('T')])])]);
        $witness = new CompletionWitness(new CompletionCosts($grammar, static fn (string $name): bool => false), new PatternProductions($grammar), new CompletionMemo());
        $plan = GenerationPlan::constrained('leaf', ['leaf' => [...array_fill(0, 260, ProductionPattern::at(0)), ProductionPattern::at(1)]]);
        self::assertNull($witness->complete([new NonTerminal('leaf')], $plan, [], true, 300));
        self::assertSame(1, $witness->complete([new NonTerminal('leaf')], $plan, ['leaf' => 260], true, 1));
    }

    public function testRememberCachesOnlyTheSuccessfulSuffixAndPreservesExactMinimumIndependence(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])])]);
        $memo = new CompletionMemo();
        $witness = new CompletionWitness(new CompletionCosts($grammar, static fn (string $name): bool => false), new PatternProductions($grammar), $memo);
        $plan = GenerationPlan::all();
        self::assertSame(4, $witness->remember([['root', 0], ['suffix', 3]], $plan, 4, 5));
        self::assertSame(1, $memo->recall($plan, 'suffix', 1, false));
        self::assertNull($memo->recall($plan, 'suffix', 1, true));
        self::assertNull($memo->recall($plan, 'root', 3, false));
    }

    public function testChoicesCachesBothOutputPossibilitiesUnderEachDistinctPattern(): void
    {
        $empty = new Production([]);
        $emitting = new Production([new Terminal('T')]);
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [$empty, $emitting])]);
        $witness = new CompletionWitness(new CompletionCosts($grammar, static fn (string $name): bool => false), new PatternProductions($grammar), new CompletionMemo());
        self::assertSame([[$empty, [0, PHP_INT_MAX]], [$emitting, [PHP_INT_MAX, 0]]], $witness->choices('leaf', null));
        self::assertSame($witness->choices('leaf', null), $witness->choices('leaf', null));
        self::assertSame([[$emitting, [PHP_INT_MAX, 0]]], $witness->choices('leaf', ProductionPattern::at(1)));
        self::assertSame([], $witness->choices('missing', null));
    }
}
