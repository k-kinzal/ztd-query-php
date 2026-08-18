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
        self::assertTrue(
            $plan->patternAt('conslist', 0)?->matches(['conslist', 'tconscomma', 'tcons']) ?? false,
        );
        self::assertTrue($plan->patternAt('conslist', 1)?->matches(['tcons']) ?? false);
        self::assertTrue($plan->patternAt('tcons', 0)?->matches(['CONSTRAINT']) ?? false);
        self::assertTrue($plan->patternAt('tcons', 1)?->matches(['FOREIGN', 'KEY']) ?? false);
        self::assertTrue($plan->patternAt('tconscomma', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('eidlist_opt', 0)?->matches(['column']) ?? false);
    }
}
