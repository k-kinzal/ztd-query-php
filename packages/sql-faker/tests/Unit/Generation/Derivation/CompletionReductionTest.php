<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\CompletionFrontier;
use SqlFaker\Generation\Derivation\CompletionMemo;
use SqlFaker\Generation\Derivation\CompletionReduction;
use SqlFaker\Generation\Derivation\CompletionState;
use SqlFaker\Generation\Derivation\ConstrainedCompletion;
use SqlFaker\Generation\Derivation\ConstraintDependencies;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(CompletionReduction::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(CompletionFrontier::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(CompletionMemo::class)]
#[UsesClass(ConstraintDependencies::class)]
#[UsesClass(ConstrainedCompletion::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\CompletionWitness::class)]
final class CompletionReductionTest extends TestCase
{
    public function testReduceKeepsBothIndependentOutputPossibilitiesAndTheirExactCosts(): void
    {
        $grammar = new Grammar('free', [
            'free' => new ProductionRule('free', [new Production([]), new Production([new NonTerminal('output')])]),
            'output' => new ProductionRule('output', [new Production([new Terminal('T')])]),
            'fixed' => new ProductionRule('fixed', [new Production([]), new Production([new Terminal('T')])]),
        ]);
        $costs = new CompletionCosts($grammar, static fn (string $name): bool => false);
        $frontier = new CompletionFrontier($costs, 10);
        $reduction = new CompletionReduction($costs, new ConstraintDependencies($grammar));
        $plan = GenerationPlan::all()->withPatternForEveryOccurrence('fixed', ProductionPattern::at(0));
        self::assertTrue($reduction->reduce(new CompletionState([new NonTerminal('free'), new NonTerminal('fixed')], [], true, 0), $plan, $frontier));
        self::assertTrue($frontier->take()?->nonEmpty);
        self::assertFalse($frontier->take()?->nonEmpty);
        self::assertNull($frontier->take());
        self::assertFalse($reduction->reduce(new CompletionState([new NonTerminal('fixed')], [], true, 0), $plan, $frontier));
        self::assertTrue($reduction->reduce(new CompletionState([new NonTerminal('free')], [], false, 3), $plan, $frontier));
        self::assertSame(4, $frontier->take()?->spent);
    }
}
