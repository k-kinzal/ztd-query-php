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
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\CompletionWitness::class)]
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
