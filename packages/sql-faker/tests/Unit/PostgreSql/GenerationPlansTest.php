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
        self::assertTrue($plan->patternAt('TableConstraint', 0)?->matches(['CONSTRAINT']) ?? false);
        self::assertTrue($plan->patternAt('ConstraintElem', 0)?->matches(['FOREIGN', 'KEY']) ?? false);
        self::assertTrue($plan->patternAt('opt_column_list', 0)?->matches(['column']) ?? false);
    }
}
