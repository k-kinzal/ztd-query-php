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

#[CoversClass(CompletionMemo::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(CompletionFrontier::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(CompletionReduction::class)]
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
#[UsesClass(\SqlFaker\Grammar\Choice\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\CompletionWitness::class)]
final class CompletionMemoTest extends TestCase
{
    public function testRecallDoesNotConfuseAnAffordableWitnessWithAnExactMinimum(): void
    {
        $memo = new CompletionMemo();
        $plan = GenerationPlan::all();
        self::assertNull($memo->recall($plan, 'pending', 20, false));
        $memo->remember($plan, 'pending', 20, 10, false);
        self::assertSame(10, $memo->recall($plan, 'pending', 10, false));
        self::assertNull($memo->recall($plan, 'pending', 9, false));
        self::assertNull($memo->recall($plan, 'pending', 20, true));
        $memo->remember($plan, 'pending', 9, 7, true);
        self::assertSame(7, $memo->recall($plan, 'pending', 20, true));
        self::assertSame(PHP_INT_MAX, $memo->recall($plan, 'pending', 6, false));
    }

    public function testRememberExhaustionDoesNotRuleOutLargerBudgetsOrAnotherPlan(): void
    {
        $memo = new CompletionMemo();
        $plan = GenerationPlan::all();
        self::assertNull($memo->recall($plan, 'pending', 5, true));
        $memo->remember($plan, 'pending', 5, PHP_INT_MAX, false);
        $memo->remember($plan, 'pending', 3, PHP_INT_MAX, true);
        self::assertSame(PHP_INT_MAX, $memo->recall($plan, 'pending', 5, true));
        self::assertNull($memo->recall($plan, 'pending', 6, true));
        self::assertNull($memo->recall(GenerationPlan::fromRule('different'), 'pending', 5, true));
    }

    public function testActivateDropsProofsForAnotherPlanEvenWhenItsPendingFormMatches(): void
    {
        $memo = new CompletionMemo();
        $first = GenerationPlan::all();
        $second = GenerationPlan::fromRule('other');
        $memo->remember($first, 'pending', 5, 3, true);
        $memo->activate($second);
        self::assertNull($memo->recall($second, 'pending', 5, true));
        $memo->remember($first, 'pending', 5, PHP_INT_MAX, false);
        self::assertNull($memo->recall($second, 'pending', 5, false));
    }
}
