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
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\CompletionWitness::class)]
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
