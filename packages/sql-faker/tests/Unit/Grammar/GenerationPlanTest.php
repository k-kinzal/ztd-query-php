<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

#[CoversClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
final class GenerationPlanTest extends TestCase
{
    public function testAllCoversTheGrammarWithoutProductionConstraints(): void
    {
        $plan = GenerationPlan::all();

        self::assertNull($plan->startRule());
        self::assertNull($plan->patternAt('statement', 0));
        self::assertSame(PHP_INT_MAX, $plan->maxDepth());
    }

    public function testFromRuleRestrictsTheGenerationRangeWithoutDirectingProductions(): void
    {
        $plan = GenerationPlan::fromRule('select_statement');

        self::assertSame('select_statement', $plan->startRule());
        self::assertNull($plan->patternAt('select_statement', 0));
    }

    public function testConstrainedDirectsEachRuleOccurrence(): void
    {
        $first = ProductionPattern::containing('CONSTRAINT');
        $second = ProductionPattern::containing('FOREIGN', 'KEY');
        $plan = GenerationPlan::constrained('create_table', ['constraint' => [$first, $second]]);

        self::assertSame('create_table', $plan->startRule());
        self::assertSame($first, $plan->patternAt('constraint', 0));
        self::assertSame($second, $plan->patternAt('constraint', 1));
        self::assertNull($plan->patternAt('constraint', 2));
        self::assertNull($plan->patternAt('unknown', 0));
    }

    public function testNonEmptyRequirementProducesANewPlan(): void
    {
        $plan = GenerationPlan::fromRule('statement');
        $required = $plan->requiringNonEmpty();

        self::assertNotSame($plan, $required);
        self::assertSame('statement', $required->startRule());
    }

    public function testMaxDepthProducesANewPlanAndNormalizesItsLowerBound(): void
    {
        $plan = GenerationPlan::fromRule('statement');
        $limited = $plan->withMaxDepth(5);
        $minimum = $plan->withMaxDepth(0);

        self::assertNotSame($plan, $limited);
        self::assertSame(PHP_INT_MAX, $plan->maxDepth());
        self::assertSame(5, $limited->maxDepth());
        self::assertSame(1, $minimum->maxDepth());
    }

    public function testRejectsAnEmptyStartRule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start rule must not be empty');

        GenerationPlan::fromRule('');
    }

    public function testRejectsAConstrainedPlanWithoutConstraints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires production patterns');

        GenerationPlan::constrained('statement', []);
    }
}
