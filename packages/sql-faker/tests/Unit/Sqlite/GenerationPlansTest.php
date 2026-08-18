<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\Sqlite\GenerationPlans;

#[CoversClass(GenerationPlans::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
final class GenerationPlansTest extends TestCase
{
    public function testForeignKeyPlanRestrictsTheTableConstraintGrammar(): void
    {
        $plan = GenerationPlans::foreignKeyConstraint();

        self::assertSame('conslist', $plan->startRule());
        self::assertNotNull($plan->patternAt('conslist', 0));
        self::assertNotNull($plan->patternAt('tcons', 1));
    }
}
