<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\PostgreSql\GenerationPlans;

#[CoversClass(GenerationPlans::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
final class GenerationPlansTest extends TestCase
{
    public function testForeignKeyPlanRestrictsTheTableConstraintGrammar(): void
    {
        $plan = GenerationPlans::foreignKeyConstraint();

        self::assertSame('TableConstraint', $plan->startRule());
        self::assertNotNull($plan->patternAt('TableConstraint', 0));
        self::assertNotNull($plan->patternAt('ConstraintElem', 0));
    }
}
