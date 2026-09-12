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

#[CoversClass(ConstraintDependencies::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(CompletionFrontier::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(CompletionMemo::class)]
#[UsesClass(CompletionReduction::class)]
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
final class ConstraintDependenciesTest extends TestCase
{
    public function testAffectedFindsRecursiveAncestorsAndDropsConsumedConstraints(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('child'), new NonTerminal('free')])]),
            'child' => new ProductionRule('child', [new Production([new NonTerminal('root')]), new Production([new Terminal('T')])]),
            'free' => new ProductionRule('free', [new Production([new Terminal('T')])]),
        ]);
        $dependencies = new ConstraintDependencies($grammar);
        $plan = GenerationPlan::constrained('root', ['child' => [ProductionPattern::at(1)]]);
        self::assertSame(['child' => true, 'root' => true], $dependencies->affected($plan, []));
        self::assertSame(['child' => true, 'root' => true], $dependencies->affected($plan, []));
        self::assertSame([], $dependencies->affected($plan, ['child' => 1]));
        self::assertSame(['free' => true, 'root' => true, 'child' => true], $dependencies->affected(GenerationPlan::all()->withPatternForEveryOccurrence('free', ProductionPattern::at(0)), ['free' => 50]));
    }
}
