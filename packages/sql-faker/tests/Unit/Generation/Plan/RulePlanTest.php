<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\Plan\RulePlan;

#[CoversClass(RulePlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(LexemeConstraint::class)]
final class RulePlanTest extends TestCase
{
    public function testAllowingRefinementsIntersectAndLeaveTheOriginalPlanIntact(): void
    {
        $original = RulePlan::any()->allowing(ProductionPattern::containing('ID'));
        $refined = $original->allowing(ProductionPattern::excluding(ProductionPattern::containing('DOT')))
            ->withLexeme('ID', LexemeConstraint::oneOf('users', 'orders'))
            ->withLexeme('ID', LexemeConstraint::oneOf('users'));
        self::assertTrue($original->pattern?->matches(['ID', 'DOT', 'ID']));
        self::assertFalse($refined->pattern?->matches(['ID', 'DOT', 'ID']));
        self::assertTrue($refined->pattern->matches(['ID']));
        self::assertSame('users', $refined->lexemes['ID']->choose(static fn (int $count): int => 0));
        self::assertSame([], $original->lexemes);
    }

    public function testWithRuleMergingPreservesChildAndListRelationships(): void
    {
        $left = RulePlan::any()->withChild('expr', 0, RulePlan::any()->allowing(ProductionPattern::exactly('ID')));
        $right = RulePlan::any()->withChild('expr', 1, RulePlan::any()->allowing(ProductionPattern::exactly('INTEGER')));
        $plan = RulePlan::any()->withRule('expr', $left)->withRule('expr', $right)
            ->withItems(RulePlan::any(), RulePlan::any());
        self::assertCount(2, $plan->rules['expr']->children['expr']);
        self::assertCount(2, $plan->items ?? []);
        self::assertSame([], $left->rules);
    }


    public function testAnyLeavesEveryKindOfChoiceUnspecified(): void
    {
        $plan = RulePlan::any();
        self::assertNull($plan->pattern);
        self::assertNull($plan->items);
        self::assertSame([], $plan->rules);
        self::assertSame([], $plan->children);
        self::assertSame([], $plan->lexemes);
    }

    public function testWithChildIntersectsOnlyTheSelectedOperand(): void
    {
        $plan = RulePlan::any()->withChild('expr', 1, RulePlan::any()->allowing(ProductionPattern::containing('ID')))
            ->withChild('expr', 1, RulePlan::any()->allowing(ProductionPattern::excluding(ProductionPattern::containing('DOT'))));
        self::assertArrayNotHasKey(0, $plan->children['expr']);
        self::assertTrue($plan->children['expr'][1]->pattern?->matches(['ID']));
        self::assertFalse($plan->children['expr'][1]->pattern->matches(['ID', 'DOT', 'ID']));
    }

    public function testWithLexemeIntersectsDomainsWithoutChangingOtherTokens(): void
    {
        $original = RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('users', 'orders'))
            ->withLexeme('STRING', LexemeConstraint::oneOf("'Alice'"));
        $refined = $original->withLexeme('ID', LexemeConstraint::oneOf('users'));
        self::assertFalse($refined->lexemes['ID']->accepts('orders'));
        self::assertTrue($original->lexemes['ID']->accepts('orders'));
        self::assertSame($original->lexemes['STRING'], $refined->lexemes['STRING']);
    }

    public function testWithItemsRetainsHeterogeneousOutputOrder(): void
    {
        $name = RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('name'));
        $score = RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('score'));
        self::assertSame([$name, $score], RulePlan::any()->withItems($name, $score)->items);
    }

    public function testMergeIntersectsCorrespondingListItems(): void
    {
        $names = RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('name', 'score'));
        $name = RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('name'));
        $score = RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('score'));
        $merged = RulePlan::any()->withItems($names, $names)->merge(RulePlan::any()->withItems($name, $score));
        self::assertEquals([$name, $score], $merged->items);
        self::assertTrue($names->lexemes['ID']->accepts('score'));
        self::assertTrue($names->lexemes['ID']->accepts('name'));
    }

}
