<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\CompletionFrontier;
use SqlFaker\Grammar\Derivation\CompletionMemo;
use SqlFaker\Grammar\Derivation\CompletionReduction;
use SqlFaker\Grammar\Derivation\CompletionState;
use SqlFaker\Grammar\Derivation\ConstrainedCompletion;
use SqlFaker\Grammar\Derivation\ConstraintDependencies;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

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
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\CompletionWitness::class)]
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
